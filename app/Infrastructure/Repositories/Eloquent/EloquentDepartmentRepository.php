<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDepartmentRepository implements DepartmentRepositoryInterface
{
    public function listByCompany(int $companyId): Collection
    {
        return Department::query()
            ->with('parent')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Department
    {
        return Department::query()
            ->with('parent')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Department
    {
        return Department::query()->create($data)->load('parent');
    }

    public function update(Department $department, array $data): Department
    {
        $department->fill($data)->save();

        return $department->refresh()->load('parent');
    }

    public function delete(Department $department): bool
    {
        return (bool) $department->delete();
    }
}
