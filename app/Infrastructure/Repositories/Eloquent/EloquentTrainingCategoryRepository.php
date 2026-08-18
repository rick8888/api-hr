<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\TrainingCategory;
use App\Domain\Platform\Repositories\TrainingCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentTrainingCategoryRepository implements TrainingCategoryRepositoryInterface
{
    public function all(): Collection
    {
        return TrainingCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return TrainingCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?TrainingCategory
    {
        return TrainingCategory::query()->find($id);
    }

    public function create(array $attributes): TrainingCategory
    {
        return TrainingCategory::query()->create($attributes);
    }

    public function update(TrainingCategory $trainingCategory, array $attributes): TrainingCategory
    {
        $trainingCategory->fill($attributes);
        $trainingCategory->save();

        return $trainingCategory->refresh();
    }

    public function delete(TrainingCategory $trainingCategory): bool
    {
        return (bool) $trainingCategory->delete();
    }
}
