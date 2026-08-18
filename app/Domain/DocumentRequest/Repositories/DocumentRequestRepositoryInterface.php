<?php

declare(strict_types=1);

namespace App\Domain\DocumentRequest\Repositories;

use App\Domain\DocumentRequest\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Collection;

interface DocumentRequestRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?DocumentRequest;

    /** @param array<string, mixed> $data */
    public function create(array $data): DocumentRequest;

    /** @param array<string, mixed> $data */
    public function update(DocumentRequest $documentRequest, array $data): DocumentRequest;

    public function delete(DocumentRequest $documentRequest): bool;
}
