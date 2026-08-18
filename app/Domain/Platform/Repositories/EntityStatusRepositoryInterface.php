<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\EntityStatus;
use Illuminate\Database\Eloquent\Collection;

interface EntityStatusRepositoryInterface
{
    /** @return Collection<int, EntityStatus> */
    public function all(?string $entityType = null): Collection;

    /** @return Collection<int, EntityStatus> */
    public function listActive(?string $entityType = null): Collection;

    public function find(int $id): ?EntityStatus;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): EntityStatus;

    /** @param array<string, mixed> $attributes */
    public function update(EntityStatus $entityStatus, array $attributes): EntityStatus;

    public function delete(EntityStatus $entityStatus): bool;
}
