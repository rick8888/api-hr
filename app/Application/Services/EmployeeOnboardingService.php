<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOnboardingTask;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeOnboardingService
{
    /** @var list<array{key: string, title: string, description: string}> */
    private const DEFAULT_TASKS = [
        [
            'key' => 'employment_documents',
            'title' => 'Collect employment documents',
            'description' => 'Collect and verify the documents required by the employer.',
        ],
        [
            'key' => 'employment_agreement',
            'title' => 'Prepare and sign employment agreement',
            'description' => 'Prepare the agreement and record its signature.',
        ],
        [
            'key' => 'payroll_setup',
            'title' => 'Set up payroll information',
            'description' => 'Confirm compensation, payment details, and payroll configuration.',
        ],
        [
            'key' => 'account_access',
            'title' => 'Set up employee access',
            'description' => 'Provision approved self-service access through the user management workflow.',
        ],
        [
            'key' => 'workplace_orientation',
            'title' => 'Complete workplace orientation',
            'description' => 'Introduce the employee to the team, workplace, and internal policies.',
        ],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Employee $employee): void
    {
        foreach (self::DEFAULT_TASKS as $task) {
            $employee->onboardingTasks()->create([
                'task_key' => $task['key'],
                'title' => $task['title'],
                'description' => $task['description'],
                'status' => EmployeeOnboardingTask::STATUS_PENDING,
            ]);
        }
    }

    /** @return Collection<int, EmployeeOnboardingTask> */
    public function tasksForActor(User $actor, Employee $employee): Collection
    {
        $this->ensureCompanyAccess($actor, $employee);

        return $employee->onboardingTasks()->orderBy('id')->get();
    }

    public function updateTaskForActor(
        User $actor,
        Employee $employee,
        EmployeeOnboardingTask $task,
        string $status,
    ): EmployeeOnboardingTask {
        $this->ensureCompanyAccess($actor, $employee);

        if ($task->employee_id !== $employee->id) {
            throw new AuthorizationException('Tâche d’intégration introuvable.');
        }

        return DB::transaction(function () use ($actor, $employee, $task, $status): EmployeeOnboardingTask {
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->id);
            $metadata = is_array($lockedEmployee->metadata) ? $lockedEmployee->metadata : [];
            $employmentStatus = $metadata['employment_status'] ?? null;
            if (! in_array($employmentStatus, ['onboarding', 'active'], true)) {
                throw ValidationException::withMessages([
                    'onboarding' => 'Le parcours d’intégration ne peut plus être modifié pour cet employé.',
                ]);
            }

            $lockedTask = EmployeeOnboardingTask::query()
                ->where('employee_id', $lockedEmployee->id)
                ->lockForUpdate()
                ->findOrFail($task->id);

            if ($lockedTask->status !== $status) {
                $lockedTask->update([
                    'status' => $status,
                    'completed_at' => $status === EmployeeOnboardingTask::STATUS_COMPLETED ? now() : null,
                    'completed_by' => $status === EmployeeOnboardingTask::STATUS_COMPLETED ? $actor->id : null,
                ]);
            }

            $allCompleted = ! $lockedEmployee->onboardingTasks()
                ->where('status', '!=', EmployeeOnboardingTask::STATUS_COMPLETED)
                ->exists();

            if ($allCompleted) {
                $metadata['employment_status'] = 'active';
                $metadata['onboarding_completed_at'] ??= now()->toIso8601String();
            } else {
                $metadata['employment_status'] = 'onboarding';
                unset($metadata['onboarding_completed_at']);
            }
            $lockedEmployee->forceFill(['metadata' => $metadata])->save();

            $this->audit->log('employee.onboarding_task_updated', $lockedTask, [
                'employee_id' => $employee->id,
                'task_key' => $lockedTask->task_key,
                'status' => $lockedTask->status,
                'onboarding_complete' => $allCompleted,
            ], userId: $actor->id);

            return $lockedTask->refresh();
        });
    }

    private function ensureCompanyAccess(User $actor, Employee $employee): void
    {
        if ($actor->company_id === null || (int) $employee->company_id !== (int) $actor->company_id) {
            throw new AuthorizationException('Employé introuvable.');
        }

        if (! $employee->onboardingTasks()->exists()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Aucun parcours d’intégration n’est associé à cet employé.',
            ]);
        }
    }
}
