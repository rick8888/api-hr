<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Loan\Models\Loan;
use App\Domain\Loan\Repositories\LoanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentLoanRepository implements LoanRepositoryInterface
{
    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $query = Loan::query()
            ->with('employee')
            ->where('company_id', $companyId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
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
            ->orderByDesc('id')
            ->get();
    }

    public function findForCompany(int $id, int $companyId): ?Loan
    {
        return Loan::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function nextCode(int $companyId): string
    {
        $year = now()->format('Y');
        $prefix = "LN-{$year}-";
        $last = Loan::query()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('code');

        $seq = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Loan
    {
        $row = Loan::query()->create($data);

        return $row->load('employee');
    }

    public function update(Loan $loan, array $data): Loan
    {
        $loan->fill($data)->save();

        return $loan->refresh()->load('employee');
    }

    public function delete(Loan $loan): bool
    {
        return (bool) $loan->delete();
    }
}
