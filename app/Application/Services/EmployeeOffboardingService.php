<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeOffboardingService
{
    /** @var list<array{key: string, title: string, description: string}> */
    private const DEFAULT_TASKS = [
        [
            'key' => 'knowledge_transfer',
            'title' => 'Complete knowledge and responsibility handover',
            'description' => 'Transfer active files, responsibilities, and essential information to the designated colleague.',
        ],
        [
            'key' => 'return_equipment',
            'title' => 'Return company equipment',
            'description' => 'Return all equipment assigned to the employee and record each return in equipment management.',
        ],
        [
            'key' => 'revoke_access',
            'title' => 'Revoke employee account access',
            'description' => 'Account tokens are revoked by the existing employee departure process; verify any other company systems.',
        ],
        [
            'key' => 'final_payroll',
            'title' => 'Finalize payroll and benefits',
            'description' => 'Review final compensation, outstanding benefits, and any required payroll adjustments.',
        ],
        [
            'key' => 'exit_documents',
            'title' => 'Verify and deliver exit documents',
            'description' => 'Verify the required departure documents have been generated and delivered.',
        ],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Employee $employee, User $actor, bool $accessRevoked = false): void
    {
        $hasAssignedEquipment = $employee->equipmentAssignments()
            ->where('status', 'assigned')
            ->exists();

        foreach (self::DEFAULT_TASKS as $task) {
            $completeImmediately = ($task['key'] === 'revoke_access' && $accessRevoked)
                || ($task['key'] === 'return_equipment' && ! $hasAssignedEquipment);

            $employee->offboardingTasks()->firstOrCreate(
                ['task_key' => $task['key']],
                [
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'status' => $completeImmediately
                        ? EmployeeOffboardingTask::STATUS_COMPLETED
                        : EmployeeOffboardingTask::STATUS_PENDING,
                    'completed_at' => $completeImmediately ? now() : null,
                    'completed_by' => $completeImmediately ? $actor->id : null,
                ],
            );
        }

        $this->audit->log('employee.offboarding_started', $employee, [
            'employee_id' => $employee->id,
            'task_count' => count(self::DEFAULT_TASKS),
        ], userId: $actor->id);
    }

    /** @return Collection<int, EmployeeOffboardingTask> */
    public function startForActor(User $actor, Employee $employee): Collection
    {
        $this->ensureCompanyAccess($actor, $employee, requireExistingChecklist: false);
        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        if ($employee->terminated_at === null && ($metadata['employment_status'] ?? null) !== 'retired') {
            throw ValidationException::withMessages([
                'employee' => 'Le parcours de départ ne peut être créé que pour un employé sorti ou retraité.',
            ]);
        }

        if (! $employee->offboardingTasks()->exists()) {
            $this->start($employee, $actor);
        }

        return $employee->offboardingTasks()->orderBy('id')->get();
    }

    /** @return Collection<int, EmployeeOffboardingTask> */
    public function listForActor(User $actor): Collection
    {
        $companyId = $this->resolveCompanyId($actor);

        return EmployeeOffboardingTask::query()
            ->with([
                'employee.equipmentAssignments.equipment',
                'employee.equipmentAssignments.employee',
                'completedBy',
            ])
            ->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();
    }

    public function updateTaskForActor(
        User $actor,
        Employee $employee,
        EmployeeOffboardingTask $task,
        string $status,
    ): EmployeeOffboardingTask {
        $this->ensureCompanyAccess($actor, $employee);

        return DB::transaction(function () use ($actor, $employee, $task, $status): EmployeeOffboardingTask {
            $lockedEmployee = Employee::query()
                ->where('company_id', $actor->company_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);
            $metadata = is_array($lockedEmployee->metadata) ? $lockedEmployee->metadata : [];
            $employmentStatus = $metadata['employment_status'] ?? null;

            if ($lockedEmployee->terminated_at === null && $employmentStatus !== 'retired') {
                throw ValidationException::withMessages([
                    'offboarding' => 'Le parcours de départ est disponible uniquement pour un employé sorti ou retraité.',
                ]);
            }

            $lockedTask = EmployeeOffboardingTask::query()
                ->where('employee_id', $lockedEmployee->id)
                ->lockForUpdate()
                ->findOrFail($task->id);

            if ($lockedTask->task_key === 'return_equipment' && $status === EmployeeOffboardingTask::STATUS_COMPLETED) {
                $hasAssignedEquipment = $lockedEmployee->equipmentAssignments()
                    ->where('status', 'assigned')
                    ->exists();
                if ($hasAssignedEquipment) {
                    throw ValidationException::withMessages([
                        'status' => 'Tous les équipements attribués doivent être retournés avant de clôturer cette étape.',
                    ]);
                }
            }

            if ($lockedTask->task_key === 'revoke_access' && $status === EmployeeOffboardingTask::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'L’accès au compte est révoqué automatiquement lors du départ et cette étape ne peut pas être rouverte.',
                ]);
            }

            if ($lockedTask->status !== $status) {
                $lockedTask->update([
                    'status' => $status,
                    'completed_at' => $status === EmployeeOffboardingTask::STATUS_COMPLETED ? now() : null,
                    'completed_by' => $status === EmployeeOffboardingTask::STATUS_COMPLETED ? $actor->id : null,
                ]);
            }

            $allCompleted = ! $lockedEmployee->offboardingTasks()
                ->where('status', '!=', EmployeeOffboardingTask::STATUS_COMPLETED)
                ->exists();

            $termination = is_array($metadata['termination'] ?? null) ? $metadata['termination'] : [];
            $retirement = is_array($metadata['retirement'] ?? null) ? $metadata['retirement'] : [];
            if ($allCompleted) {
                if ($termination !== []) {
                    $termination['offboarding_completed_at'] ??= now()->toIso8601String();
                    $metadata['termination'] = $termination;
                } else {
                    $retirement['offboarding_completed_at'] ??= now()->toIso8601String();
                    $metadata['retirement'] = $retirement;
                }
            } else {
                unset($termination['offboarding_completed_at'], $retirement['offboarding_completed_at']);
                if ($termination !== []) {
                    $metadata['termination'] = $termination;
                }
                if ($retirement !== []) {
                    $metadata['retirement'] = $retirement;
                }
            }
            $lockedEmployee->forceFill(['metadata' => $metadata])->save();

            $this->audit->log('employee.offboarding_task_updated', $lockedTask, [
                'employee_id' => $lockedEmployee->id,
                'task_key' => $lockedTask->task_key,
                'status' => $lockedTask->status,
                'offboarding_complete' => $allCompleted,
            ], userId: $actor->id);

            return $lockedTask->refresh();
        });
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! $actor->hasPermission('employees.manage')) {
            throw new AuthorizationException('Accès non autorisé au parcours de départ.');
        }

        return (int) $actor->company_id;
    }

    private function ensureCompanyAccess(
        User $actor,
        Employee $employee,
        bool $requireExistingChecklist = true,
    ): void {
        if (
            $actor->company_id === null
            || ! $actor->hasPermission('employees.manage')
            || (int) $employee->company_id !== (int) $actor->company_id
        ) {
            throw new AuthorizationException('Employé introuvable.');
        }

        if ($requireExistingChecklist && ! $employee->offboardingTasks()->exists()) {
            throw ValidationException::withMessages([
                'offboarding' => 'Aucun parcours de départ n’est associé à cet employé.',
            ]);
        }
    }
}
