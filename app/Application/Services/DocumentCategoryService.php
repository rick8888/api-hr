<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\DocumentCategoryData;
use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Domain\Platform\Models\DocumentCategory;
use App\Domain\Platform\Repositories\DocumentCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DocumentCategoryService
{
    public function __construct(
        private readonly DocumentCategoryRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, DocumentCategory> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, DocumentCategory> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(DocumentCategoryData $data): DocumentCategory
    {
        return DB::transaction(function () use ($data) {
            $category = $this->repository->create($data->toModelArray());
            $this->audit->log('document_category.created', $category, ['code' => $category->code]);

            return $category;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(DocumentCategory $documentCategory, array $validated): DocumentCategory
    {
        return DB::transaction(function () use ($documentCategory, $validated) {
            $payload = array_merge($this->modelToArray($documentCategory), $validated);

            if ($documentCategory->is_system) {
                $payload['code'] = $documentCategory->code;
            }

            $updated = $this->repository->update(
                $documentCategory,
                DocumentCategoryData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('document_category.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(DocumentCategory $documentCategory, bool $isActive): DocumentCategory
    {
        if ($documentCategory->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Une catégorie système ne peut pas être désactivée.',
            ]);
        }

        return $this->update($documentCategory, ['is_active' => $isActive]);
    }

    public function delete(DocumentCategory $documentCategory): bool
    {
        if ($documentCategory->is_system) {
            throw ValidationException::withMessages([
                'document_category' => 'Une catégorie système ne peut pas être supprimée.',
            ]);
        }

        if (EmployeeDocument::query()->where('category', $documentCategory->code)->exists()) {
            throw ValidationException::withMessages([
                'document_category' => 'Cette catégorie est utilisée par des documents et ne peut pas être supprimée.',
            ]);
        }

        return DB::transaction(function () use ($documentCategory) {
            $this->audit->log('document_category.deleted', $documentCategory, ['code' => $documentCategory->code]);

            return $this->repository->delete($documentCategory);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(DocumentCategory $documentCategory): array
    {
        return [
            'name' => $documentCategory->name,
            'code' => $documentCategory->code,
            'description' => $documentCategory->description,
            'sort_order' => $documentCategory->sort_order,
            'is_system' => $documentCategory->is_system,
            'is_active' => $documentCategory->is_active,
        ];
    }
}
