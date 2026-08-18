<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Domain\Payroll\Models\SalaryComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SalaryComponentRepositoryInterface
{
    public function paginate(
        int $perPage = 15,
        ?int $companyId = null,
        bool $includeStatutory = false,
    ): LengthAwarePaginator;

    /** @return Collection<int, SalaryComponent> */
    public function listForCompany(int $companyId, bool $includeStatutory = false): Collection;

    public function find(int $id): ?SalaryComponent;

    /** @param array<string, mixed> $data */
    public function create(array $data): SalaryComponent;

    /** @param array<string, mixed> $data */
    public function update(SalaryComponent $component, array $data): SalaryComponent;

    public function delete(SalaryComponent $component): bool;
}
