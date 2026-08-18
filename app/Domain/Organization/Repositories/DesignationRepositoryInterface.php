<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Models\Designation;
use Illuminate\Database\Eloquent\Collection;

interface DesignationRepositoryInterface
{
    /** @return Collection<int, Designation> */
    public function listByCompany(int $companyId): Collection;

    public function findForCompany(int $id, int $companyId): ?Designation;

    /** @param array<string, mixed> $data */
    public function create(array $data): Designation;

    /** @param array<string, mixed> $data */
    public function update(Designation $designation, array $data): Designation;

    public function delete(Designation $designation): bool;
}
