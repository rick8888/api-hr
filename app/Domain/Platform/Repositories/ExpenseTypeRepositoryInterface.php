<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\ExpenseType;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseTypeRepositoryInterface
{
    /** @return Collection<int, ExpenseType> */
    public function all(): Collection;

    /** @return Collection<int, ExpenseType> */
    public function listActive(): Collection;

    public function find(int $id): ?ExpenseType;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ExpenseType;

    /** @param array<string, mixed> $attributes */
    public function update(ExpenseType $expenseType, array $attributes): ExpenseType;

    public function delete(ExpenseType $expenseType): bool;
}
