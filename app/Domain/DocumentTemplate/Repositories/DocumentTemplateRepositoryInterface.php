<?php

declare(strict_types=1);

namespace App\Domain\DocumentTemplate\Repositories;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Collection;

interface DocumentTemplateRepositoryInterface
{
    /** @return Collection<int, DocumentTemplate> */
    public function listForAdmin(?int $companyId, bool $includePlatform): Collection;

    /** @return Collection<int, DocumentTemplate> */
    public function listPublishedForCompany(int $companyId, ?string $channel = null): Collection;

    public function findById(int $id): ?DocumentTemplate;

    public function findPublishedByCode(int $companyId, string $code, string $locale = 'fr'): ?DocumentTemplate;

    public function create(array $attributes): DocumentTemplate;

    public function update(DocumentTemplate $template, array $attributes): DocumentTemplate;

    public function delete(DocumentTemplate $template): bool;
}
