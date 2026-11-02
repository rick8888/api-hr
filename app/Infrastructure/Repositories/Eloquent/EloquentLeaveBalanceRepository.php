<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveBalanceAdjustment;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Models\LeaveType;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class EloquentLeaveBalanceRepository implements LeaveBalanceRepositoryInterface
{
    public function employeesForCompany(int $companyId, array $filters = []): Collection
    {
        $employeeIds = isset($filters['employee_ids']) && is_array($filters['employee_ids'])
            ? array_values(array_map('intval', $filters['employee_ids']))
            : null;

        // Liste vide explicite (ex. manager sans équipe).
        if ($employeeIds === []) {
            return new Collection();
        }

        return Employee::query()
            ->where('company_id', $companyId)
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('id', $employeeIds))
            // Même règle que la fiche employés : un compte tenant n'est pas du staff RH.
            ->where(function ($q) {
                $q->whereNull('metadata->role')
                    ->orWhere('metadata->role', '<>', 'tenant');
            })
            ->whereNotExists(function ($q) use ($companyId) {
                $q->selectRaw('1')
                    ->from('users')
                    ->where('users.company_id', $companyId)
                    ->where('users.role', 'tenant')
                    ->whereRaw('LOWER(users.email) = LOWER(employees.email)');
            })
            // Afficher tout le staff non soft-deleted (actifs, inactifs, suspendus).
            // Les soldes restent utiles même après suspension / inactivation.
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
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->startOfDay();
        $requests = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        $usedDays = [];
        foreach ($requests as $request) {
            $key = $request->employee_id.'|'.strtoupper((string) $request->leave_type);
            $usedDays[$key] = ($usedDays[$key] ?? 0.0) + $request->daysWithinPeriod($from, $to);
        }

        return $usedDays;
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
