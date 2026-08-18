<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Domain\Payroll\Models\PayrollModifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PayrollModifierRepositoryInterface
{
    public function paginate(int $perPage = 50, ?int $companyId = null): LengthAwarePaginator;

    /** @return Collection<int, PayrollModifier> */
    public function listForCompany(int $companyId): Collection;

    public function find(int $id): ?PayrollModifier;

    /** @param array<string, mixed> $data */
    public function create(array $data): PayrollModifier;

    /** @param array<string, mixed> $data */
    public function update(PayrollModifier $modifier, array $data): PayrollModifier;

    public function delete(PayrollModifier $modifier): bool;
}
