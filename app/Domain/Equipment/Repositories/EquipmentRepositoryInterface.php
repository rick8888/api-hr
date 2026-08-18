<?php

declare(strict_types=1);

namespace App\Domain\Equipment\Repositories;

use App\Domain\Equipment\Models\Equipment;
use Illuminate\Database\Eloquent\Collection;

interface EquipmentRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Equipment;

    /** @param array<string, mixed> $data */
    public function create(array $data): Equipment;

    /** @param array<string, mixed> $data */
    public function update(Equipment $equipment, array $data): Equipment;

    public function delete(Equipment $equipment): bool;
}
