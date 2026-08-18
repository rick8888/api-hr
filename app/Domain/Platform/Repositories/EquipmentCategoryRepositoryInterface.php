<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\EquipmentCategory;
use Illuminate\Database\Eloquent\Collection;

interface EquipmentCategoryRepositoryInterface
{
    /** @return Collection<int, EquipmentCategory> */
    public function all(): Collection;

    /** @return Collection<int, EquipmentCategory> */
    public function listActive(): Collection;

    public function find(int $id): ?EquipmentCategory;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): EquipmentCategory;

    /** @param array<string, mixed> $attributes */
    public function update(EquipmentCategory $equipmentCategory, array $attributes): EquipmentCategory;

    public function delete(EquipmentCategory $equipmentCategory): bool;
}
