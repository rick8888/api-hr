<?php

declare(strict_types=1);

namespace App\Domain\Employee\Repositories;

use App\Domain\Employee\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $companyId = null): LengthAwarePaginator;

    /**
     * @param  array{search?: string, department?: string, structure_status?: string}  $filters
     * @return Collection<int, Employee>
     */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function find(int $id): ?Employee;

    public function findForCompany(int $id, int $companyId): ?Employee;

    /** @param array<string, mixed> $data */
    public function create(array $data): Employee;

    /** @param array<string, mixed> $data */
    public function update(Employee $employee, array $data): Employee;

    public function delete(Employee $employee): bool;
}
