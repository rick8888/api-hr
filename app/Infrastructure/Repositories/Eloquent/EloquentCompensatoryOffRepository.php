<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Leave\Models\CompensatoryOff;
use App\Domain\Leave\Repositories\CompensatoryOffRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentCompensatoryOffRepository implements CompensatoryOffRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = CompensatoryOff::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['from'])) {
            $query->whereDate('worked_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('worked_date', '<=', $filters['to']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['usage_status'])) {
            $query->where('usage_status', $filters['usage_status']);
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['employee_code'])) {
            $query->whereHas('employee', function ($employeeQuery) use ($filters) {
                $employeeQuery->where('matricule', $filters['employee_code']);
            });
        }

        return $query
            ->orderByDesc('worked_date')
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?CompensatoryOff
    {
        return CompensatoryOff::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): CompensatoryOff
    {
        $row = CompensatoryOff::query()->create($data);

        return $row->load('employee');
    }

    public function update(CompensatoryOff $compensatoryOff, array $data): CompensatoryOff
    {
        $compensatoryOff->fill($data)->save();

        return $compensatoryOff->refresh()->load('employee');
    }

    public function delete(CompensatoryOff $compensatoryOff): bool
    {
        return (bool) $compensatoryOff->delete();
    }
}
