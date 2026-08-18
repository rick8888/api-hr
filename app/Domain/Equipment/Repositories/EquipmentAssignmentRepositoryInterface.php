<?php

declare(strict_types=1);

namespace App\Domain\Equipment\Repositories;

use App\Domain\Equipment\Models\EquipmentAssignment;
use Illuminate\Database\Eloquent\Collection;

interface EquipmentAssignmentRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?EquipmentAssignment;

    /** @param array<string, mixed> $data */
    public function create(array $data): EquipmentAssignment;

    /** @param array<string, mixed> $data */
    public function update(EquipmentAssignment $assignment, array $data): EquipmentAssignment;

    public function delete(EquipmentAssignment $assignment): bool;
}
