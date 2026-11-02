<?php

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveBalanceAdjustment;
use Illuminate\Database\Eloquent\Collection;

interface LeaveBalanceRepositoryInterface
{
    /**
     * @param  array{employee_ids?: list<int>}  $filters
     * @return Collection<int, Employee>
     */
    public function employeesForCompany(int $companyId, array $filters = []): Collection;

    /** @return Collection<int, \App\Domain\Leave\Models\LeaveType> */
    public function activeLeaveTypesForCompany(int $companyId): Collection;

    /** @return Collection<int, LeaveBalance> */
    public function balancesForCompanyAndYear(int $companyId, int $year): Collection;

    /** @return array<string, float> Keys use "employee_id|leave_type_code". */
    public function usedDaysForCompanyAndYear(int $companyId, int $year): array;

    public function findEmployeeByCode(int $companyId, string $employeeCode): ?Employee;

    /** @return Collection<int, LeaveBalance> */
    public function balancesForEmployeeAndYear(int $companyId, int $employeeId, int $year): Collection;

    public function upsert(
        int $companyId,
        int $employeeId,
        int $leaveTypeId,
        int $year,
        array $values,
    ): LeaveBalance;

    public function createAdjustment(array $values): LeaveBalanceAdjustment;

    /** @return Collection<int, LeaveBalanceAdjustment> */
    public function adjustmentsForEmployeeAndYear(int $companyId, int $employeeId, int $year): Collection;
}
