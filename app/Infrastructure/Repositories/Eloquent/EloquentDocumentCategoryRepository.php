<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\DocumentCategory;
use App\Domain\Platform\Repositories\DocumentCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentDocumentCategoryRepository implements DocumentCategoryRepositoryInterface
{
    public function all(): Collection
    {
        return DocumentCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return DocumentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?DocumentCategory
    {
        return DocumentCategory::query()->find($id);
    }

    public function create(array $attributes): DocumentCategory
    {
        return DocumentCategory::query()->create($attributes);
    }

    public function update(DocumentCategory $documentCategory, array $attributes): DocumentCategory
    {
        $documentCategory->fill($attributes);
        $documentCategory->save();

        return $documentCategory->refresh();
    }

    public function delete(DocumentCategory $documentCategory): bool
    {
        return (bool) $documentCategory->delete();
    }
}
