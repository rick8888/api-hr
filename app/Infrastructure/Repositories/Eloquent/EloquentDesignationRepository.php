<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Organization\Models\Designation;
use App\Domain\Organization\Repositories\DesignationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDesignationRepository implements DesignationRepositoryInterface
{
    public function listByCompany(int $companyId): Collection
    {
        return Designation::query()
            ->with('department')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Designation
    {
        return Designation::query()
            ->with('department')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Designation
    {
        $designation = Designation::query()->create($data);

        return $designation->load('department');
    }

    public function update(Designation $designation, array $data): Designation
    {
        $designation->fill($data)->save();

        return $designation->refresh()->load('department');
    }

    public function delete(Designation $designation): bool
    {
        return (bool) $designation->delete();
    }
}
