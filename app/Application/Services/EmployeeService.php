<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Employee\EmployeeData;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

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
        return $this->repository->listByCompany($this->resolveCompanyId($actor), $filters);
    }

    public function find(int $id): ?Employee
    {
        return $this->repository->find($id);
    }

    public function findForActor(User $actor, Employee $employee): Employee
    {
        $this->ensureBelongsToCompany($employee, $this->resolveCompanyId($actor));

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

        unset(
            $validated['create_user'],
            $validated['role'],
            $validated['password'],
            $validated['use_default_password'],
        );

        if ($role) {
            $metadata = is_array($validated['metadata'] ?? null) ? $validated['metadata'] : [];
            $metadata['role'] = $role;
            $validated['metadata'] = $metadata;
        }

        return DB::transaction(function () use ($validated, $createUser, $role, $password, $useDefaultPassword, $companyId) {
            $employee = $this->create(EmployeeData::fromArray($validated));

            if ($createUser && $role && ! in_array($role, ['super_admin', 'tenant'], true)) {
                $this->createLoginUser($employee, $companyId, $role, $password, $useDefaultPassword);
            }

            return $employee->fresh();
        });
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

        $before = $this->modelToArray($employee);

        if (array_key_exists('metadata', $validated) && is_array($validated['metadata'])) {
            $existing = is_array($employee->metadata) ? $employee->metadata : [];
            $validated['metadata'] = array_merge($existing, $validated['metadata']);
        }

        $current = array_merge($before, $validated);
        $current['company_id'] = $companyId;

        return $this->update(
            $employee,
            EmployeeData::fromArray($current),
            $this->diffFields($before, $current)
        );
    }

    public function toggleStatusForActor(User $actor, Employee $employee): Employee
    {
        return $this->updateForActor($actor, $employee, ['is_active' => ! $employee->is_active]);
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
