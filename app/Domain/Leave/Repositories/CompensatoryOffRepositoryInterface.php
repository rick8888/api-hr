<?php

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Models\CompensatoryOff;
use Illuminate\Database\Eloquent\Collection;

interface CompensatoryOffRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?CompensatoryOff;

    /** @param array<string, mixed> $data */
    public function create(array $data): CompensatoryOff;

    /** @param array<string, mixed> $data */
    public function update(CompensatoryOff $compensatoryOff, array $data): CompensatoryOff;

    public function delete(CompensatoryOff $compensatoryOff): bool;
}
