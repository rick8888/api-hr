<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Models\Department;
use Illuminate\Database\Eloquent\Collection;

interface DepartmentRepositoryInterface
{
    /** @return Collection<int, Department> */
    public function listByCompany(int $companyId): Collection;

    public function findForCompany(int $id, int $companyId): ?Department;

    /** @param array<string, mixed> $data */
    public function create(array $data): Department;

    /** @param array<string, mixed> $data */
    public function update(Department $department, array $data): Department;

    public function delete(Department $department): bool;
}
