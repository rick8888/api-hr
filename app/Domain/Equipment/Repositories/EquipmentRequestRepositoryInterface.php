<?php

declare(strict_types=1);

namespace App\Domain\Equipment\Repositories;

use App\Domain\Equipment\Models\EquipmentRequest;
use Illuminate\Database\Eloquent\Collection;

interface EquipmentRequestRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?EquipmentRequest;

    /** @param array<string, mixed> $data */
    public function create(array $data): EquipmentRequest;

    /** @param array<string, mixed> $data */
    public function update(EquipmentRequest $request, array $data): EquipmentRequest;

    public function delete(EquipmentRequest $request): bool;
}
