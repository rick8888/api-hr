<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Equipment\Models\Equipment;
use App\Domain\Equipment\Repositories\EquipmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEquipmentRepository implements EquipmentRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Equipment::query()->where('company_id', $companyId);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('is_active', $active);
            }
        }
        if (! empty($filters['available_only'])) {
            $query->where('stock', '>', 0);
        }

        return $query->orderBy('name')->get();
    }

    public function findForCompany(int $id, int $companyId): ?Equipment
    {
        return Equipment::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Equipment
    {
        return Equipment::query()->create($data);
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        $equipment->fill($data)->save();

        return $equipment->refresh();
    }

    public function delete(Equipment $equipment): bool
    {
        return (bool) $equipment->delete();
    }
}
