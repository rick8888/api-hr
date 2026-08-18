<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Organization\Models\Holiday;
use App\Domain\Organization\Repositories\HolidayRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentHolidayRepository implements HolidayRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Holiday::query()->where('company_id', $companyId);

        if (! empty($filters['year'])) {
            $query->whereYear('date', (int) $filters['year']);
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('is_active', $active);
            }
        }

        if (
            array_key_exists('visible_to_employees', $filters)
            && $filters['visible_to_employees'] !== null
            && $filters['visible_to_employees'] !== ''
        ) {
            $visible = filter_var(
                $filters['visible_to_employees'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );
            if ($visible !== null) {
                $query->where('visible_to_employees', $visible);
            }
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query
            ->orderBy('date')
            ->orderBy('name')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Holiday
    {
        return Holiday::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Holiday
    {
        return Holiday::query()->create($data);
    }

    public function update(Holiday $holiday, array $data): Holiday
    {
        $holiday->fill($data)->save();

        return $holiday->refresh();
    }

    public function delete(Holiday $holiday): bool
    {
        return (bool) $holiday->delete();
    }
}
