<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\TrainingCategoryData;
use App\Domain\Platform\Models\TrainingCategory;
use App\Domain\Platform\Repositories\TrainingCategoryRepositoryInterface;
use App\Domain\Training\Models\Training;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrainingCategoryService
{
    public function __construct(
        private readonly TrainingCategoryRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, TrainingCategory> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, TrainingCategory> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(TrainingCategoryData $data): TrainingCategory
    {
        return DB::transaction(function () use ($data) {
            $category = $this->repository->create($data->toModelArray());
            $this->audit->log('training_category.created', $category, ['code' => $category->code]);

            return $category;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(TrainingCategory $trainingCategory, array $validated): TrainingCategory
    {
        return DB::transaction(function () use ($trainingCategory, $validated) {
            $payload = array_merge($this->modelToArray($trainingCategory), $validated);

            if ($trainingCategory->is_system) {
                $payload['code'] = $trainingCategory->code;
            }

            $updated = $this->repository->update(
                $trainingCategory,
                TrainingCategoryData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('training_category.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(TrainingCategory $trainingCategory, bool $isActive): TrainingCategory
    {
        if ($trainingCategory->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Une catégorie système ne peut pas être désactivée.',
            ]);
        }

        return $this->update($trainingCategory, ['is_active' => $isActive]);
    }

    public function delete(TrainingCategory $trainingCategory): bool
    {
        if ($trainingCategory->is_system) {
            throw ValidationException::withMessages([
                'training_category' => 'Une catégorie système ne peut pas être supprimée.',
            ]);
        }

        if (Training::query()->where('category', $trainingCategory->code)->exists()) {
            throw ValidationException::withMessages([
                'training_category' => 'Cette catégorie est utilisée par des formations et ne peut pas être supprimée.',
            ]);
        }

        return DB::transaction(function () use ($trainingCategory) {
            $this->audit->log('training_category.deleted', $trainingCategory, ['code' => $trainingCategory->code]);

            return $this->repository->delete($trainingCategory);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(TrainingCategory $trainingCategory): array
    {
        return [
            'name' => $trainingCategory->name,
            'code' => $trainingCategory->code,
            'description' => $trainingCategory->description,
            'sort_order' => $trainingCategory->sort_order,
            'is_system' => $trainingCategory->is_system,
            'is_active' => $trainingCategory->is_active,
        ];
    }
}
