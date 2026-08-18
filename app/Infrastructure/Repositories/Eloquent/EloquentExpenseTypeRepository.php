<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\ExpenseType;
use App\Domain\Platform\Repositories\ExpenseTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentExpenseTypeRepository implements ExpenseTypeRepositoryInterface
{
    public function all(): Collection
    {
        return ExpenseType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return ExpenseType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?ExpenseType
    {
        return ExpenseType::query()->find($id);
    }

    public function create(array $attributes): ExpenseType
    {
        return ExpenseType::query()->create($attributes);
    }

    public function update(ExpenseType $expenseType, array $attributes): ExpenseType
    {
        $expenseType->fill($attributes);
        $expenseType->save();

        return $expenseType->refresh();
    }

    public function delete(ExpenseType $expenseType): bool
    {
        return (bool) $expenseType->delete();
    }
}
