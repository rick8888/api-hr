<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Attendance\Models\Regularization;
use App\Domain\Attendance\Repositories\RegularizationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentRegularizationRepository implements RegularizationRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Regularization::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['from'])) {
            $query->whereDate('attendance_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('attendance_date', '<=', $filters['to']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['employee_ids']) && is_array($filters['employee_ids'])) {
            $ids = array_values(array_map('intval', $filters['employee_ids']));
            if ($ids === []) {
                return new Collection();
            }
            $query->whereIn('employee_id', $ids);
        }

        return $query
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Regularization
    {
        return Regularization::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Regularization
    {
        $regularization = Regularization::query()->create($data);

        return $regularization->load('employee');
    }

    public function update(Regularization $regularization, array $data): Regularization
    {
        $regularization->fill($data)->save();

        return $regularization->refresh()->load('employee');
    }

    public function delete(Regularization $regularization): bool
    {
        return (bool) $regularization->delete();
    }
}
