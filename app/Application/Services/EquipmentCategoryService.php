<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\EquipmentCategoryData;
use App\Domain\Equipment\Models\Equipment;
use App\Domain\Platform\Models\EquipmentCategory;
use App\Domain\Platform\Repositories\EquipmentCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EquipmentCategoryService
{
    public function __construct(
        private readonly EquipmentCategoryRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, EquipmentCategory> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, EquipmentCategory> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(EquipmentCategoryData $data): EquipmentCategory
    {
        return DB::transaction(function () use ($data) {
            $category = $this->repository->create($data->toModelArray());
            $this->audit->log('equipment_category.created', $category, ['code' => $category->code]);

            return $category;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(EquipmentCategory $equipmentCategory, array $validated): EquipmentCategory
    {
        return DB::transaction(function () use ($equipmentCategory, $validated) {
            $payload = array_merge($this->modelToArray($equipmentCategory), $validated);

            if ($equipmentCategory->is_system) {
                $payload['code'] = $equipmentCategory->code;
            }

            $updated = $this->repository->update(
                $equipmentCategory,
                EquipmentCategoryData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('equipment_category.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(EquipmentCategory $equipmentCategory, bool $isActive): EquipmentCategory
    {
        if ($equipmentCategory->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Une catégorie système ne peut pas être désactivée.',
            ]);
        }

        return $this->update($equipmentCategory, ['is_active' => $isActive]);
    }

    public function delete(EquipmentCategory $equipmentCategory): bool
    {
        if ($equipmentCategory->is_system) {
            throw ValidationException::withMessages([
                'equipment_category' => 'Une catégorie système ne peut pas être supprimée.',
            ]);
        }

        if (Equipment::query()->where('category', $equipmentCategory->code)->exists()) {
            throw ValidationException::withMessages([
                'equipment_category' => 'Cette catégorie est utilisée par des matériels et ne peut pas être supprimée.',
            ]);
        }

        return DB::transaction(function () use ($equipmentCategory) {
            $this->audit->log('equipment_category.deleted', $equipmentCategory, ['code' => $equipmentCategory->code]);

            return $this->repository->delete($equipmentCategory);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(EquipmentCategory $equipmentCategory): array
    {
        return [
            'name' => $equipmentCategory->name,
            'code' => $equipmentCategory->code,
            'description' => $equipmentCategory->description,
            'sort_order' => $equipmentCategory->sort_order,
            'is_system' => $equipmentCategory->is_system,
            'is_active' => $equipmentCategory->is_active,
        ];
    }
}
