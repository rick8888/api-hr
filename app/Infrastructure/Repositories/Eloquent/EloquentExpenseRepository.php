<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Expense\Models\Expense;
use App\Domain\Expense\Repositories\ExpenseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentExpenseRepository implements ExpenseRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Expense::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['date'])) {
            $query->whereDate('expense_date', $filters['date']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('expense_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('expense_date', '<=', $filters['to']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['employee_code'])) {
            $query->whereHas('employee', function ($employeeQuery) use ($filters) {
                $employeeQuery->where('matricule', $filters['employee_code']);
            });
        }

        return $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Expense
    {
        return Expense::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Expense
    {
        $row = Expense::query()->create($data);

        return $row->load('employee');
    }

    public function update(Expense $expense, array $data): Expense
    {
        $expense->fill($data)->save();

        return $expense->refresh()->load('employee');
    }

    public function delete(Expense $expense): bool
    {
        return (bool) $expense->delete();
    }
}
