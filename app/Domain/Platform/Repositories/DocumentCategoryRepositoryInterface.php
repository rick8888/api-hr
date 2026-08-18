<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Collection;

interface DocumentCategoryRepositoryInterface
{
    /** @return Collection<int, DocumentCategory> */
    public function all(): Collection;

    /** @return Collection<int, DocumentCategory> */
    public function listActive(): Collection;

    public function find(int $id): ?DocumentCategory;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DocumentCategory;

    /** @param array<string, mixed> $attributes */
    public function update(DocumentCategory $documentCategory, array $attributes): DocumentCategory;

    public function delete(DocumentCategory $documentCategory): bool;
}
