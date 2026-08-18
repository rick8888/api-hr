<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveBalanceAdjustment;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Models\LeaveType;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentLeaveBalanceRepository implements LeaveBalanceRepositoryInterface
{
    public function employeesForCompany(int $companyId): Collection
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function activeLeaveTypesForCompany(int $companyId): Collection
    {
        return LeaveType::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function balancesForCompanyAndYear(int $companyId, int $year): Collection
    {
        return LeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->get();
    }

    public function usedDaysForCompanyAndYear(int $companyId, int $year): array
    {
        return LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->selectRaw('employee_id, leave_type, SUM(total_days) as used_days')
            ->groupBy('employee_id', 'leave_type')
            ->get()
            ->mapWithKeys(fn (LeaveRequest $request) => [
                $request->employee_id.'|'.strtoupper((string) $request->leave_type)
                    => (float) $request->used_days,
            ])
            ->all();
    }

    public function findEmployeeByCode(int $companyId, string $employeeCode): ?Employee
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('matricule', $employeeCode)
            ->first();
    }

    public function balancesForEmployeeAndYear(int $companyId, int $employeeId, int $year): Collection
    {
        return LeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get();
    }

    public function upsert(
        int $companyId,
        int $employeeId,
        int $leaveTypeId,
        int $year,
        array $values,
    ): LeaveBalance {
        return LeaveBalance::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            $values,
        );
    }

    public function createAdjustment(array $values): LeaveBalanceAdjustment
    {
        return LeaveBalanceAdjustment::query()->create($values);
    }

    public function adjustmentsForEmployeeAndYear(int $companyId, int $employeeId, int $year): Collection
    {
        return LeaveBalanceAdjustment::query()
            ->with(['leaveType:id,name,code', 'adjustedBy:id,name'])
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->latest()
            ->get();
    }
}
