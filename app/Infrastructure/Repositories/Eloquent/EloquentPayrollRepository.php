<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentPayrollRepository implements PayrollRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $companyId = null, array $filters = []): LengthAwarePaginator
    {
        $period = isset($filters['period']) ? trim((string) $filters['period']) : '';
        $periodFrom = isset($filters['period_from']) ? trim((string) $filters['period_from']) : '';
        $periodTo = isset($filters['period_to']) ? trim((string) $filters['period_to']) : '';
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : 0;
        $department = isset($filters['department']) ? trim((string) $filters['department']) : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $statuses = $this->normalizeStatuses($filters['status'] ?? null);

        return Payroll::query()
            ->with(['employee'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($period !== '', fn ($q) => $q->where('period', $period))
            ->when($periodFrom !== '', fn ($q) => $q->where('period', '>=', $periodFrom))
            ->when($periodTo !== '', fn ($q) => $q->where('period', '<=', $periodTo))
            ->when($employeeId > 0, fn ($q) => $q->where('employee_id', $employeeId))
            ->when($statuses !== [], fn ($q) => $q->whereIn('status', $statuses))
            ->when($department !== '', function ($q) use ($department) {
                $q->whereHas('employee', fn ($eq) => $eq->where('department', $department));
            })
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($outer) use ($like) {
                    $outer->where('period', 'like', $like)
                        ->orWhere('id', 'like', $like)
                        ->orWhereHas('employee', function ($eq) use ($like) {
                            $eq->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('matricule', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('department', 'like', $like);
                        });
                });
            })
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  string|array<int, string>|null  $status
     * @return list<string>
     */
    private function normalizeStatuses(string|array|null $status): array
    {
        if ($status === null || $status === '') {
            return [];
        }

        $list = is_array($status)
            ? $status
            : (preg_split('/[,\|]/', (string) $status) ?: []);

        return array_values(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $list
        )));
    }

    public function find(int $id): ?Payroll
    {
        return Payroll::query()->with(['employee', 'company', 'country', 'lines'])->find($id);
    }

    public function findByEmployeeAndPeriod(int $employeeId, string $period): ?Payroll
    {
        return Payroll::query()
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->first();
    }

    public function create(array $data): Payroll
    {
        return Payroll::query()->create($data);
    }

    public function update(Payroll $payroll, array $data): Payroll
    {
        $payroll->fill($data)->save();

        return $payroll->refresh();
    }

    public function delete(Payroll $payroll): bool
    {
        return (bool) $payroll->delete();
    }
}
