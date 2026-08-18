<?php

declare(strict_types=1);

namespace App\Domain\Expense\Repositories;

use App\Domain\Expense\Models\Expense;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Expense;

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense;

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data): Expense;

    public function delete(Expense $expense): bool;
}
