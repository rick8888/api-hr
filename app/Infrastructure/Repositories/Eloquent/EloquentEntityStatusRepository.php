<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\EntityStatus;
use App\Domain\Platform\Repositories\EntityStatusRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEntityStatusRepository implements EntityStatusRepositoryInterface
{
    public function all(?string $entityType = null): Collection
    {
        return EntityStatus::query()
            ->when($entityType, fn ($q) => $q->where('entity_type', $entityType))
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(?string $entityType = null): Collection
    {
        return EntityStatus::query()
            ->where('is_active', true)
            ->when($entityType, fn ($q) => $q->where('entity_type', $entityType))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?EntityStatus
    {
        return EntityStatus::query()->find($id);
    }

    public function create(array $attributes): EntityStatus
    {
        return EntityStatus::query()->create($attributes);
    }

    public function update(EntityStatus $entityStatus, array $attributes): EntityStatus
    {
        $entityStatus->fill($attributes);
        $entityStatus->save();

        return $entityStatus->refresh();
    }

    public function delete(EntityStatus $entityStatus): bool
    {
        return (bool) $entityStatus->delete();
    }
}
