<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\DayType;
use App\Domain\Platform\Repositories\DayTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDayTypeRepository implements DayTypeRepositoryInterface
{
    public function all(): Collection
    {
        return DayType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return DayType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?DayType
    {
        return DayType::query()->find($id);
    }

    public function create(array $attributes): DayType
    {
        return DayType::query()->create($attributes);
    }

    public function update(DayType $dayType, array $attributes): DayType
    {
        $dayType->fill($attributes);
        $dayType->save();

        return $dayType->refresh();
    }

    public function delete(DayType $dayType): bool
    {
        return (bool) $dayType->delete();
    }
}
