<?php

declare(strict_types=1);

namespace App\Domain\Company\Repositories;

use App\Domain\Company\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $countryId = null): LengthAwarePaginator;

    public function find(int $id): ?Company;

    /** @param array<string, mixed> $data */
    public function create(array $data): Company;

    /** @param array<string, mixed> $data */
    public function update(Company $company, array $data): Company;

    public function delete(Company $company): bool;
}
