<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Employee\EmployeeData;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
        private readonly TeamScopeService $teamScope,
        private readonly DocumentTemplateMailService $templateMail,
        private readonly EmployeeLifecycleDocumentService $lifecycleDocuments,
        private readonly TenantPlanQuotaService $planQuotas,
        private readonly EmployeeOffboardingService $offboarding,
    ) {}

    public function paginate(int $perPage = 15, ?int $companyId = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $companyId);
    }

    /**
     * @param  array{search?: string, department?: string, structure_status?: string}  $filters
     * @return Collection<int, Employee>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor);
        $scopedFilters = $this->teamScope->mergeEmployeeListFilters($actor, $filters);
        if ($scopedFilters === null) {
            return new Collection;
        }

        return $this->repository->listByCompany($companyId, $scopedFilters);
    }

    public function find(int $id): ?Employee
    {
        return $this->repository->find($id);
    }

    public function findForActor(User $actor, Employee $employee): Employee
    {
        $this->ensureBelongsToCompany($employee, $this->resolveCompanyId($actor));
        Gate::forUser($actor)->authorize('view', $employee);

        return $employee;
    }

    public function create(EmployeeData $data): Employee
    {
        $employee = $this->repository->create($data->toModelArray());
        $this->audit->log('employee.created', $employee, [
            'matricule' => $employee->matricule,
            'employee_id' => $employee->id,
        ]);

        return $employee;
    }

    /** @param array<string, mixed> $validated */
    public function createForActor(User $actor, array $validated): Employee
    {
        $companyId = $this->resolveCompanyId($actor);
        $validated['company_id'] = $companyId;
        $validated['country_id'] = $validated['country_id']
            ?? Company::query()->whereKey($companyId)->value('country_id');

        if (empty($validated['country_id'])) {
            throw ValidationException::withMessages([
                'country_id' => 'Impossible de déterminer le pays de l\'employé.',
            ]);
        }

        $createUser = (bool) ($validated['create_user'] ?? true);
        $role = $validated['role'] ?? null;
        $password = $validated['password'] ?? null;
        $useDefaultPassword = (bool) ($validated['use_default_password'] ?? false);

        if (in_array($role, ['super_admin', 'tenant'], true)) {
            throw ValidationException::withMessages([
                'role' => 'Le rôle tenant / super_admin ne peut pas être assigné à une fiche employé.',
            ]);
        }

        $notifyWelcome = (bool) ($validated['notify_by_email'] ?? $validated['send_welcome_email'] ?? false);

        unset(
            $validated['create_user'],
            $validated['role'],
            $validated['password'],
            $validated['use_default_password'],
            $validated['notify_by_email'],
            $validated['send_welcome_email'],
        );

        if ($role) {
            $metadata = is_array($validated['metadata'] ?? null) ? $validated['metadata'] : [];
            $metadata['role'] = $role;
            $validated['metadata'] = $metadata;
        }

        $employee = DB::transaction(function () use ($validated, $createUser, $role, $password, $useDefaultPassword, $companyId) {
            $metadata = is_array($validated['metadata'] ?? null) ? $validated['metadata'] : null;
            if (
                (bool) ($validated['is_active'] ?? true)
                && $this->planQuotas->countsTowardEmployeeQuota(
                    $companyId,
                    $metadata,
                    isset($validated['email']) ? (string) $validated['email'] : null,
                )
            ) {
                $this->planQuotas->assertCanAddResources($companyId, employees: 1);
            }

            $employee = $this->create(EmployeeData::fromArray($validated));

            if ($createUser && $role && ! in_array($role, ['super_admin', 'tenant'], true)) {
                $this->createLoginUser($employee, $companyId, $role, $password, $useDefaultPassword);
            }

            return $employee->fresh();
        });

        if ($notifyWelcome && $employee !== null) {
            $this->templateMail->sendForEvent($employee, 'employee.welcome');
        }

        return $employee;
    }

    /** @param list<string> $changedFields */
    public function update(Employee $employee, EmployeeData $data, array $changedFields = []): Employee
    {
        $updated = $this->repository->update($employee, $data->toModelArray());
        $this->audit->log('employee.updated', $updated, [
            'matricule' => $updated->matricule,
            'employee_id' => $updated->id,
            'changed' => $changedFields,
        ]);

        return $updated;
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Employee $employee, array $validated): Employee
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($employee, $companyId);
        Gate::forUser($actor)->authorize('update', $employee);

        $before = $this->modelToArray($employee);
        $validated = $this->restrictUpdatePayloadForActor($actor, $validated, $before);

        if (array_key_exists('metadata', $validated) && is_array($validated['metadata'])) {
            $existing = is_array($employee->metadata) ? $employee->metadata : [];
            $validated['metadata'] = array_merge($existing, $validated['metadata']);
        }

        $current = array_merge($before, $validated);
        $current['company_id'] = $companyId;

        return DB::transaction(function () use ($employee, $before, $current): Employee {
            $wasCounted = (bool) ($before['is_active'] ?? false)
                && $this->planQuotas->countsTowardEmployeeQuota(
                    (int) $employee->company_id,
                    is_array($before['metadata'] ?? null) ? $before['metadata'] : null,
                    isset($before['email']) ? (string) $before['email'] : null,
                );
            $willBeCounted = (bool) ($current['is_active'] ?? true)
                && $this->planQuotas->countsTowardEmployeeQuota(
                    (int) $employee->company_id,
                    is_array($current['metadata'] ?? null) ? $current['metadata'] : null,
                    isset($current['email']) ? (string) $current['email'] : null,
                );

            if (! $wasCounted && $willBeCounted) {
                $this->planQuotas->assertCanAddResources((int) $employee->company_id, employees: 1);
            }

            $updated = $this->update(
                $employee,
                EmployeeData::fromArray($current),
                $this->diffFields($before, $current)
            );

            if (($before['is_active'] ?? false) && ! ($current['is_active'] ?? true)) {
                $this->revokeLinkedUserAccess($updated);
            }

            return $updated;
        });
    }

    public function updateAvatarForActor(
        User $actor,
        Employee $employee,
        UploadedFile $avatar,
    ): Employee {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($employee, $companyId);
        Gate::forUser($actor)->authorize('update', $employee);

        if (! $actor->hasPermission('employees.manage')) {
            throw new AuthorizationException('Seuls les administrateurs peuvent modifier la photo.');
        }

        $path = $avatar->store("employees/{$companyId}/{$employee->id}/avatar", 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Impossible d’enregistrer la photo de l’employé.');
        }

        $previousPath = $employee->avatar_path;
        $employee->avatar_path = $path;

        try {
            $employee->saveOrFail();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        $this->audit->log('employee.avatar_updated', $employee, [
            'employee_id' => $employee->id,
        ]);

        return $employee->fresh();
    }

    public function toggleStatusForActor(User $actor, Employee $employee): Employee
    {
        return $this->updateForActor($actor, $employee, ['is_active' => ! $employee->is_active]);
    }

    /**
     * Active / inactive groupé (pas suspension, fin de contrat ni retraite).
     *
     * @param  list<int>  $ids
     * @return array{updated: list<Employee>, skipped: list<array{id: int, reason: string}>}
     */
    public function bulkSetStatusForActor(User $actor, array $ids, string $status): array
    {
        $companyId = $this->resolveCompanyId($actor);
        $wantActive = $status === 'active';
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($ids, $employees, $wantActive, $companyId, $status): array {
            if ($wantActive) {
                $quotaIncrements = $employees->filter(function (Employee $employee): bool {
                    if ($employee->is_active || $employee->terminated_at !== null || $employee->isRetired()) {
                        return false;
                    }

                    $metadata = is_array($employee->metadata) ? $employee->metadata : [];

                    return $this->planQuotas->countsTowardEmployeeQuota(
                        (int) $employee->company_id,
                        $metadata,
                        $employee->email,
                    );
                })->count();

                if ($quotaIncrements > 0) {
                    $this->planQuotas->assertCanAddResources($companyId, employees: $quotaIncrements);
                }
            }

            $updated = [];
            $skipped = [];

            foreach ($ids as $id) {
                /** @var Employee|null $employee */
                $employee = $employees->get($id);
                if ($employee === null) {
                    $skipped[] = ['id' => $id, 'reason' => 'not_found'];

                    continue;
                }

                if ($employee->terminated_at !== null) {
                    $skipped[] = ['id' => $id, 'reason' => 'terminated'];

                    continue;
                }

                if ($employee->isRetired()) {
                    $skipped[] = ['id' => $id, 'reason' => 'retired'];

                    continue;
                }

                $payload = ['is_active' => $wantActive];
                $metadata = is_array($employee->metadata) ? $employee->metadata : [];
                $clearEmploymentOverlay = $wantActive
                    || (($metadata['employment_status'] ?? null) === 'suspended');

                if ($clearEmploymentOverlay) {
                    unset($metadata['employment_status'], $metadata['suspension']);
                    $payload['metadata'] = $metadata;
                }

                // Remplacement direct de metadata (updateForActor fusionne et ne peut pas retirer de clés).
                $updatedEmployee = $this->repository->update($employee, $payload);
                if (! $wantActive && $employee->is_active) {
                    $this->revokeLinkedUserAccess($updatedEmployee);
                }
                $this->audit->log('employee.status_bulk_updated', $updatedEmployee, [
                    'matricule' => $updatedEmployee->matricule,
                    'employee_id' => $updatedEmployee->id,
                    'status' => $status,
                ]);
                $updated[] = $updatedEmployee;
            }

            return ['updated' => $updated, 'skipped' => $skipped];
        });
    }

    /**
     * @param  array{suspension_date: string, duration?: int|null, reason: string, notify_by_email?: bool}  $validated
     */
    public function suspendForActor(User $actor, Employee $employee, array $validated): Employee
    {
        $this->ensureBelongsToCompany($employee, $this->resolveCompanyId($actor));
        Gate::forUser($actor)->authorize('update', $employee);
        $this->ensureCanSuspend($actor, $employee);

        $date = (string) $validated['suspension_date'];
        $duration = isset($validated['duration']) ? (int) $validated['duration'] : null;
        if ($duration !== null && $duration < 1) {
            $duration = null;
        }
        $reason = trim((string) $validated['reason']);
        $notify = (bool) ($validated['notify_by_email'] ?? false);
        $endsAt = $duration !== null
            ? Carbon::parse($date)->addDays($duration)->toDateString()
            : null;

        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $metadata['employment_status'] = 'suspended';
        $metadata['suspension'] = [
            'date' => $date,
            'duration_days' => $duration,
            'ends_at' => $endsAt,
            'reason' => $reason,
            'notify_by_email' => $notify,
            'at' => now()->toIso8601String(),
            'by_user_id' => $actor->id,
        ];

        $updated = DB::transaction(function () use ($employee, $metadata, $reason, $date, $duration, $notify) {
            $updated = $this->repository->update($employee, [
                'is_active' => false,
                'metadata' => $metadata,
            ]);

            $this->audit->log('employee.suspended', $updated, [
                'matricule' => $updated->matricule,
                'employee_id' => $updated->id,
                'status' => 'suspended',
                'reason' => $reason,
                'suspension_date' => $date,
                'duration_days' => $duration,
                'notify_by_email' => $notify,
            ]);

            $this->revokeLinkedUserAccess($updated);

            return $updated;
        });

        if ($notify) {
            $this->sendLifecycleEmail($updated, 'employee.suspended');
        }

        return $updated;
    }

    /**
     * @param  array{type: string, rehire_eligible?: bool, exit_date: string, last_working_day: string, reason: string, notify_by_email?: bool, generate_termination_letter?: bool}  $validated
     */
    public function terminateForActor(User $actor, Employee $employee, array $validated): Employee
    {
        $this->ensureBelongsToCompany($employee, $this->resolveCompanyId($actor));
        Gate::forUser($actor)->authorize('update', $employee);
        $this->ensureCanTerminate($actor, $employee);

        $type = (string) $validated['type'];
        $rehire = (bool) ($validated['rehire_eligible'] ?? false);
        $exitDate = (string) $validated['exit_date'];
        $lastWorkingDay = (string) $validated['last_working_day'];
        $reason = trim((string) $validated['reason']);
        $notify = (bool) ($validated['notify_by_email'] ?? false);
        $generateLetter = (bool) ($validated['generate_termination_letter'] ?? false);

        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $metadata['employment_status'] = $type === 'retirement' ? 'retired' : 'terminated';
        $metadata['termination'] = [
            'type' => $type,
            'rehire_eligible' => $rehire,
            'exit_date' => $exitDate,
            'last_working_day' => $lastWorkingDay,
            'reason' => $reason,
            'notify_by_email' => $notify,
            'generate_termination_letter' => $generateLetter,
            'at' => now()->toIso8601String(),
            'by_user_id' => $actor->id,
        ];

        $updated = DB::transaction(function () use ($actor, $employee, $metadata, $type, $rehire, $exitDate, $lastWorkingDay, $reason, $notify, $generateLetter) {
            $updated = $this->repository->update($employee, [
                'is_active' => false,
                'terminated_at' => $exitDate,
                'metadata' => $metadata,
            ]);

            $this->audit->log('employee.terminated', $updated, [
                'matricule' => $updated->matricule,
                'employee_id' => $updated->id,
                'status' => $updated->terminated_at ? 'terminated' : 'inactive',
                'reason' => $reason,
                'type' => $type,
                'rehire_eligible' => $rehire,
                'exit_date' => $exitDate,
                'last_working_day' => $lastWorkingDay,
                'notify_by_email' => $notify,
                'generate_termination_letter' => $generateLetter,
            ]);

            $this->revokeLinkedUserAccess($updated);
            $this->offboarding->start($updated, $actor, accessRevoked: true);

            return $updated;
        });

        if ($generateLetter) {
            $this->lifecycleDocuments->generatePdfForEvent($actor, $updated, 'employee.terminated');
        }

        if ($notify) {
            $this->sendLifecycleEmail($updated, 'employee.terminated');
        }

        return $updated;
    }

    public function retireForActor(User $actor, Employee $employee): Employee
    {
        $this->ensureBelongsToCompany($employee, $this->resolveCompanyId($actor));
        Gate::forUser($actor)->authorize('update', $employee);
        $this->ensureCanRetire($actor, $employee);

        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $metadata['employment_status'] = 'retired';
        $metadata['retirement'] = [
            'at' => now()->toIso8601String(),
            'date' => now()->toDateString(),
            'by_user_id' => $actor->id,
        ];

        return DB::transaction(function () use ($actor, $employee, $metadata) {
            $updated = $this->repository->update($employee, [
                'is_active' => false,
                'metadata' => $metadata,
            ]);

            $this->audit->log('employee.retired', $updated, [
                'matricule' => $updated->matricule,
                'employee_id' => $updated->id,
                'status' => 'retired',
            ]);

            $this->revokeLinkedUserAccess($updated);
            $this->offboarding->start($updated, $actor, accessRevoked: true);

            return $updated;
        });
    }

    public function delete(Employee $employee): bool
    {
        $this->audit->log('employee.deleted', $employee, ['matricule' => $employee->matricule]);

        return $this->repository->delete($employee);
    }

    public function deleteForActor(User $actor, Employee $employee): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($employee, $companyId);
        Gate::forUser($actor)->authorize('delete', $employee);

        return DB::transaction(fn () => $this->delete($employee));
    }

    private function createLoginUser(
        Employee $employee,
        int $companyId,
        string $role,
        ?string $password,
        bool $useDefaultPassword,
    ): void {
        if (! $employee->email) {
            throw ValidationException::withMessages([
                'email' => 'Un email est requis pour créer le compte utilisateur.',
            ]);
        }

        if (User::query()->where('email', $employee->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Cet email est déjà utilisé par un compte utilisateur.',
            ]);
        }

        $resolvedPassword = $useDefaultPassword || blank($password) ? '12345678' : $password;

        User::query()->create([
            'name' => trim($employee->first_name.' '.$employee->last_name),
            'email' => $employee->email,
            'password' => $resolvedPassword,
            'role' => $role,
            'company_id' => $companyId,
        ]);
    }

    private function resolveCompanyId(User $actor): int
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin', 'team_manager'], true)) {
            throw new AuthorizationException('Accès non autorisé aux employés.');
        }

        return (int) $actor->company_id;
    }

    private function ensureBelongsToCompany(Employee $employee, int $companyId): void
    {
        if ($employee->company_id !== $companyId) {
            throw new AuthorizationException('Employé introuvable.');
        }
    }

    /**
     * Manager d'équipe : infos de travail uniquement (shift, équipe, etc.).
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $before
     * @return array<string, mixed>
     */
    private function restrictUpdatePayloadForActor(User $actor, array $validated, array $before): array
    {
        if ($actor->hasPermission('employees.manage')) {
            return $validated;
        }

        if ($actor->role !== 'team_manager') {
            return $validated;
        }

        $allowedRoot = ['job_title', 'department', 'hired_at', 'metadata'];
        $validated = array_intersect_key($validated, array_flip($allowedRoot));

        if (! isset($validated['metadata']) || ! is_array($validated['metadata'])) {
            return $validated;
        }

        $allowedMeta = [
            'designation',
            'department',
            'team',
            'reporting_manager',
            'reporting_manager_id',
            'shift',
            'shift_id',
            'attendance_type',
            'qr_group',
        ];
        $validated['metadata'] = array_intersect_key($validated['metadata'], array_flip($allowedMeta));
        unset($validated['metadata']['role']);

        return $validated;
    }

    private function ensureCanSuspend(User $actor, Employee $employee): void
    {
        if ($employee->terminated_at !== null) {
            throw ValidationException::withMessages([
                'employee' => 'Impossible de suspendre un employé dont le contrat est terminé.',
            ]);
        }

        if ($employee->isSuspended()) {
            throw ValidationException::withMessages([
                'employee' => 'Cet employé est déjà suspendu.',
            ]);
        }

        if ($employee->isRetired()) {
            throw ValidationException::withMessages([
                'employee' => 'Impossible de suspendre un employé à la retraite.',
            ]);
        }

        $this->ensureNotSelf($actor, $employee);
    }

    private function ensureCanTerminate(User $actor, Employee $employee): void
    {
        if ($employee->terminated_at !== null) {
            throw ValidationException::withMessages([
                'employee' => 'Le contrat de cet employé est déjà terminé.',
            ]);
        }

        if ($employee->isRetired()) {
            throw ValidationException::withMessages([
                'employee' => 'Cet employé est déjà à la retraite.',
            ]);
        }

        $this->ensureNotSelf($actor, $employee);
    }

    private function ensureCanRetire(User $actor, Employee $employee): void
    {
        if ($employee->terminated_at !== null) {
            throw ValidationException::withMessages([
                'employee' => 'Impossible de mettre à la retraite un employé dont le contrat est terminé.',
            ]);
        }

        if ($employee->isRetired()) {
            throw ValidationException::withMessages([
                'employee' => 'Cet employé est déjà à la retraite.',
            ]);
        }

        $this->ensureNotSelf($actor, $employee);
    }

    private function ensureNotSelf(User $actor, Employee $employee): void
    {
        $linkedUserId = is_array($employee->metadata) ? ($employee->metadata['linked_user_id'] ?? null) : null;
        $sameEmail = $employee->email && strcasecmp((string) $employee->email, (string) $actor->email) === 0;
        if ($sameEmail || (int) $linkedUserId === (int) $actor->id) {
            throw ValidationException::withMessages([
                'employee' => 'Vous ne pouvez pas effectuer cette action sur votre propre compte.',
            ]);
        }
    }

    private function revokeLinkedUserAccess(Employee $employee): void
    {
        $user = $this->findLinkedUser($employee);
        if ($user === null || $user->role === 'super_admin') {
            return;
        }

        $user->tokens()->delete();
    }

    private function findLinkedUser(Employee $employee): ?User
    {
        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $linkedId = $metadata['linked_user_id'] ?? null;

        if ($linkedId) {
            $byId = User::query()->find((int) $linkedId);
            if ($byId !== null) {
                return $byId;
            }
        }

        if (! $employee->email || ! $employee->company_id) {
            return null;
        }

        return User::query()
            ->where('company_id', $employee->company_id)
            ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $employee->email))])
            ->first();
    }

    private function sendLifecycleEmail(Employee $employee, string $eventKey): void
    {
        if (! $this->templateMail->sendForEvent($employee->fresh(), $eventKey)) {
            Log::info('E-mail cycle de vie non envoyé (modèle absent ou e-mail invalide).', [
                'employee_id' => $employee->id,
                'event' => $eventKey,
            ]);
        }
    }

    /**
     * Champs réellement modifiés, metadata incluse (à plat) pour l'audit.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function diffFields(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if ($key === 'metadata') {
                continue;
            }
            if (! array_key_exists($key, $before) || $before[$key] != $value) {
                $changed[] = $key;
            }
        }

        $beforeMeta = is_array($before['metadata'] ?? null) ? $before['metadata'] : [];
        $afterMeta = is_array($after['metadata'] ?? null) ? $after['metadata'] : [];

        foreach ($afterMeta as $key => $value) {
            if (! array_key_exists($key, $beforeMeta) || $beforeMeta[$key] != $value) {
                $changed[] = $key;
            }
        }

        return array_values(array_unique($changed));
    }

    /** @return array<string, mixed> */
    private function modelToArray(Employee $employee): array
    {
        return [
            'company_id' => $employee->company_id,
            'country_id' => $employee->country_id,
            'matricule' => $employee->matricule,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'job_title' => $employee->job_title,
            'department' => $employee->department,
            'contract_type' => $employee->contract_type,
            'base_salary' => $employee->base_salary,
            'currency' => $employee->currency,
            'hired_at' => optional($employee->hired_at)->toDateString(),
            'date_of_birth' => optional($employee->date_of_birth)->toDateString(),
            'national_id' => $employee->national_id,
            'social_security_number' => $employee->social_security_number,
            'tax_number' => $employee->tax_number,
            'metadata' => $employee->metadata,
            'is_active' => $employee->is_active,
        ];
    }
}
