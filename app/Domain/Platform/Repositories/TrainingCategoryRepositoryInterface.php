<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\TrainingCategory;
use Illuminate\Database\Eloquent\Collection;

interface TrainingCategoryRepositoryInterface
{
    /** @return Collection<int, TrainingCategory> */
    public function all(): Collection;

    /** @return Collection<int, TrainingCategory> */
    public function listActive(): Collection;

    public function find(int $id): ?TrainingCategory;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TrainingCategory;

    /** @param array<string, mixed> $attributes */
    public function update(TrainingCategory $trainingCategory, array $attributes): TrainingCategory;

    public function delete(TrainingCategory $trainingCategory): bool;
}
