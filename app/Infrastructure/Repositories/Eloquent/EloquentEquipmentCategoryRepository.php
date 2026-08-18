<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\EquipmentCategory;
use App\Domain\Platform\Repositories\EquipmentCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEquipmentCategoryRepository implements EquipmentCategoryRepositoryInterface
{
    public function all(): Collection
    {
        return EquipmentCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return EquipmentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?EquipmentCategory
    {
        return EquipmentCategory::query()->find($id);
    }

    public function create(array $attributes): EquipmentCategory
    {
        return EquipmentCategory::query()->create($attributes);
    }

    public function update(EquipmentCategory $equipmentCategory, array $attributes): EquipmentCategory
    {
        $equipmentCategory->fill($attributes);
        $equipmentCategory->save();

        return $equipmentCategory->refresh();
    }

    public function delete(EquipmentCategory $equipmentCategory): bool
    {
        return (bool) $equipmentCategory->delete();
    }
}
