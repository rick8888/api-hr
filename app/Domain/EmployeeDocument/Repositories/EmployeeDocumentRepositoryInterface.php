<?php

declare(strict_types=1);

namespace App\Domain\EmployeeDocument\Repositories;

use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeDocumentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, EmployeeDocument>
     */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?EmployeeDocument;

    /** @param array<string, mixed> $data */
    public function create(array $data): EmployeeDocument;

    /** @param array<string, mixed> $data */
    public function update(EmployeeDocument $document, array $data): EmployeeDocument;

    public function delete(EmployeeDocument $document): bool;
}
