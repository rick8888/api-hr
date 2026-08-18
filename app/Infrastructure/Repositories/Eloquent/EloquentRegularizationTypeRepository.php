<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\RegularizationType;
use App\Domain\Platform\Repositories\RegularizationTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentRegularizationTypeRepository implements RegularizationTypeRepositoryInterface
{
    public function all(): Collection
    {
        return RegularizationType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return RegularizationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?RegularizationType
    {
        return RegularizationType::query()->find($id);
    }

    public function create(array $attributes): RegularizationType
    {
        return RegularizationType::query()->create($attributes);
    }

    public function update(RegularizationType $regularizationType, array $attributes): RegularizationType
    {
        $regularizationType->fill($attributes);
        $regularizationType->save();

        return $regularizationType->refresh();
    }

    public function delete(RegularizationType $regularizationType): bool
    {
        return (bool) $regularizationType->delete();
    }
}
