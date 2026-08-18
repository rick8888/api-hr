<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Payroll\Repositories\EmployeeSalaryStructureRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentEmployeeSalaryStructureRepository implements EmployeeSalaryStructureRepositoryInterface
{
    public function listForEmployee(int $employeeId): Collection
    {
        return EmployeeSalaryStructure::query()
            ->where('employee_id', $employeeId)
            ->with(['lines.component'])
            ->orderByDesc('effective_from')
            ->get();
    }

    public function find(int $id): ?EmployeeSalaryStructure
    {
        return EmployeeSalaryStructure::query()->with(['lines.component'])->find($id);
    }

    public function findActiveForPeriod(int $employeeId, string $period): ?EmployeeSalaryStructure
    {
        $start = $period . '-01';

        return EmployeeSalaryStructure::query()
            ->where('employee_id', $employeeId)
            ->where('status', EmployeeSalaryStructure::STATUS_ACTIVE)
            ->where('effective_from', '<=', $start)
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start);
            })
            ->orderByDesc('effective_from')
            ->with(['lines.component'])
            ->first();
    }

    public function create(array $data): EmployeeSalaryStructure
    {
        return EmployeeSalaryStructure::query()->create($data);
    }

    public function update(EmployeeSalaryStructure $structure, array $data): EmployeeSalaryStructure
    {
        $structure->fill($data)->save();
        return $structure->refresh();
    }

    public function archivePrevious(int $employeeId, string $effectiveFrom): void
    {
        EmployeeSalaryStructure::query()
            ->where('employee_id', $employeeId)
            ->where('status', EmployeeSalaryStructure::STATUS_ACTIVE)
            ->where('effective_from', '<', $effectiveFrom)
            ->whereNull('effective_to')
            ->update([
                'effective_to' => date('Y-m-d', strtotime($effectiveFrom . ' -1 day')),
            ]);
    }

    public function delete(EmployeeSalaryStructure $structure): bool
    {
        return (bool) $structure->delete();
    }
}
