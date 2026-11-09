<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Services\PayrollService;
use App\Domain\Attendance\Models\AttendanceBreak;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Regularization;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Domain\Employee\Models\Employee;
use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Domain\Equipment\Models\Equipment;
use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Domain\Equipment\Models\EquipmentRequest;
use App\Domain\Expense\Models\Expense;
use App\Domain\Leave\Models\CompensatoryOff;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveBalanceAdjustment;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Models\LeaveType;
use App\Domain\Loan\Models\Loan;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Designation;
use App\Domain\Organization\Models\Holiday;
use App\Domain\Organization\Models\Site;
use App\Domain\Organization\Models\Team;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Recruitment\Models\Candidate;
use App\Domain\Recruitment\Models\JobOpening;
use App\Domain\Training\Models\Training;
use App\Domain\Training\Models\TrainingEnrollment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

final class DemoHrDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $country = Country::query()->findOrFail($company->country_id);

        foreach ([
            ['code' => 'ENGINEERING', 'name' => 'Ingénierie'],
            ['code' => 'HR', 'name' => 'Ressources humaines'],
            ['code' => 'OPERATIONS', 'name' => 'Opérations'],
        ] as $data) {
            Department::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                ['name' => $data['name'], 'is_active' => true],
            );
        }

        Team::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PRODUCT'],
            ['name' => 'Équipe Produit', 'description' => 'Équipe de démonstration', 'is_active' => true],
        );

        $this->seedDesignationsAndHolidays($company);

        $site = Site::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CASA-HQ'],
            [
                'country_id' => $country->id,
                'name' => 'Siège de Casablanca',
                'city' => 'Casablanca',
                'address' => '45 Bd Mohammed V',
                'is_active' => true,
            ],
        );

        $shift = Shift::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DAY'],
            [
                'name' => 'Journée standard',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                'is_active' => true,
            ],
        );

        $employeeData = [
            [
                'matricule' => 'EMP-0002',
                'first_name' => 'Brooklyn',
                'last_name' => 'Simmons',
                'email' => 'employee@bravorh.com',
                'job_title' => 'Responsable produit',
                'department' => 'Ingénierie',
                'salary' => 14500,
                'hired_at' => '2022-05-10',
            ],
            [
                'matricule' => 'EMP-0003',
                'first_name' => 'Amina',
                'last_name' => 'El Idrissi',
                'email' => 'amina.elidrissi@example.ma',
                'job_title' => 'Responsable RH',
                'department' => 'Ressources humaines',
                'salary' => 11000,
                'hired_at' => '2021-09-01',
            ],
            [
                'matricule' => 'EMP-0004',
                'first_name' => 'Omar',
                'last_name' => 'Bennani',
                'email' => 'omar.bennani@example.ma',
                'job_title' => 'Coordinateur opérations',
                'department' => 'Opérations',
                'salary' => 9000,
                'hired_at' => '2024-02-12',
            ],
            [
                'matricule' => 'EMP-0005',
                'first_name' => 'Salma',
                'last_name' => 'Tazi',
                'email' => 'salma.tazi@example.ma',
                'job_title' => 'Développeuse',
                'department' => 'Ingénierie',
                'salary' => 12500,
                'hired_at' => '2023-06-05',
            ],
        ];

        $employees = collect();
        $primaryEmployee = Employee::query()
            ->where('company_id', $company->id)
            ->where('matricule', 'EMP-0001')
            ->first();

        if ($primaryEmployee !== null) {
            $employees->push($primaryEmployee);
        }

        foreach ($employeeData as $data) {
            $employee = Employee::query()->updateOrCreate(
                ['matricule' => $data['matricule']],
                [
                    'company_id' => $company->id,
                    'country_id' => $country->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'job_title' => $data['job_title'],
                    'department' => $data['department'],
                    'contract_type' => 'CDI',
                    'hired_at' => $data['hired_at'],
                    'base_salary' => $data['salary'],
                    'currency' => $company->currency,
                    'is_active' => true,
                ],
            );
            $employees->push($employee);

            EmployeeSalaryStructure::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'name' => 'Structure démo 2026'],
                [
                    'base_salary' => $data['salary'],
                    'currency' => $company->currency,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_to' => null,
                    'status' => EmployeeSalaryStructure::STATUS_ACTIVE,
                ],
            );
        }

        $this->seedAttendance($company, $employees, $site, $shift);
        $this->seedAttendanceBreaks($company);
        $this->seedOvertimeRegularizations($company);
        $this->seedLeaveData($company, $employees);
        $this->seedLeaveBalanceAdjustments($company, $employees);
        $this->seedCompensatoryOffs($company, $employees);
        $this->seedExpensesAndLoans($company, $employees);
        $this->seedTrainingAndRecruitment($company, $employees);
        $this->seedDocumentsAndEquipment($company, $employees);
        $this->seedPayroll($employees);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function demoSelfServiceEmployee(Collection $employees): Employee
    {
        $employee = $employees->firstWhere('email', 'employee@bravorh.com') ?? $employees->first();

        if (! $employee instanceof Employee) {
            throw new RuntimeException('Demo employee collection is empty.');
        }

        return $employee;
    }

    private function seedDesignationsAndHolidays(Company $company): void
    {
        foreach ([
            ['code' => 'PRODUCT_MANAGER', 'name' => 'Responsable produit', 'department' => 'ENGINEERING'],
            ['code' => 'HR_MANAGER', 'name' => 'Responsable RH', 'department' => 'HR'],
            ['code' => 'OPS_COORDINATOR', 'name' => 'Coordinateur opérations', 'department' => 'OPERATIONS'],
            ['code' => 'PHP_DEVELOPER', 'name' => 'Développeur PHP', 'department' => 'ENGINEERING'],
        ] as $data) {
            $departmentId = Department::query()
                ->where('company_id', $company->id)
                ->where('code', $data['department'])
                ->value('id');

            Designation::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                [
                    'department_id' => $departmentId,
                    'name' => $data['name'],
                    'description' => 'Poste de démonstration.',
                    'is_active' => true,
                ],
            );
        }

        $christmas = Carbon::create(now()->year, 12, 25);
        if ($christmas->isPast()) {
            $christmas->addYear();
        }

        foreach ([
            [
                'code' => 'DEMO_NATIONAL_HOLIDAY',
                'name' => 'Jour férié national (démo)',
                'date' => $christmas->toDateString(),
                'type' => 'public',
                'category' => 'national',
                'recurring' => true,
                'optional_holiday' => false,
                'half_day' => false,
            ],
            [
                'code' => 'DEMO_COMPANY_DAY',
                'name' => 'Journée de démonstration',
                'date' => now()->addDays(30)->toDateString(),
                'type' => 'company',
                'category' => 'corporate',
                'recurring' => false,
                'optional_holiday' => true,
                'half_day' => true,
            ],
        ] as $data) {
            Holiday::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                [
                    ...$data,
                    'description' => 'Événement fictif du jeu de données de démonstration.',
                    'color' => $data['type'] === 'public' ? '#22c55e' : '#6366f1',
                    'applicable_to' => 'all',
                    'restricted_holiday' => false,
                    'visible_to_employees' => true,
                    'send_notification' => false,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedAttendance(Company $company, Collection $employees, Site $site, Shift $shift): void
    {
        $date = Carbon::now()->subMonthNoOverflow()->endOfMonth()->startOfDay();
        $periodStart = $date->copy()->startOfMonth();
        $workingDaysSeeded = 0;

        while ($date->greaterThanOrEqualTo($periodStart)) {
            if ($date->isWeekday()) {
                foreach ($employees as $index => $employee) {
                    $isLate = $workingDaysSeeded === 2 && $index === 0;
                    $overtimeMinutes = match (true) {
                        $workingDaysSeeded === 2 && $index === 0 => 120,
                        $workingDaysSeeded === 4 && $index === 1 => 60,
                        $workingDaysSeeded === 6 && $index === 2 => 90,
                        $workingDaysSeeded === 8 && $index === 3 => 120,
                        default => 0,
                    };

                    $this->upsertAttendanceRecord(
                        $company,
                        $employee,
                        $shift,
                        $site,
                        $date,
                        $isLate,
                        $overtimeMinutes,
                    );
                }

                $workingDaysSeeded++;
            }

            $date->subDay();
        }

        $date = Carbon::today();
        $currentMonthStart = $date->copy()->startOfMonth();
        $currentMonthWorkingDaysSeeded = 0;

        while ($currentMonthWorkingDaysSeeded < 5 && $date->greaterThanOrEqualTo($currentMonthStart)) {
            if ($date->isWeekday()) {
                foreach ($employees as $index => $employee) {
                    $isLate = $currentMonthWorkingDaysSeeded === 0 && $index === 0;
                    $overtimeMinutes = match (true) {
                        $currentMonthWorkingDaysSeeded === 1 && $index === 1 => 60,
                        $currentMonthWorkingDaysSeeded === 3 && $index === 2 => 90,
                        default => 0,
                    };

                    $this->upsertAttendanceRecord(
                        $company,
                        $employee,
                        $shift,
                        $site,
                        $date,
                        $isLate,
                        $overtimeMinutes,
                    );
                }

                $currentMonthWorkingDaysSeeded++;
            }

            $date->subDay();
        }
    }

    private function upsertAttendanceRecord(
        Company $company,
        Employee $employee,
        Shift $shift,
        Site $site,
        Carbon $date,
        bool $isLate,
        int $overtimeMinutes,
    ): void {
        $attendanceDate = $date->toDateString();
        $record = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $attendanceDate)
            ->first();

        if ($record === null) {
            $record = new AttendanceRecord([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate,
            ]);
        }

        $record->fill([
            'shift_id' => $shift->id,
            'site_id' => $site->id,
            'check_in' => $isLate ? '09:18:00' : '08:55:00',
            'check_out' => Carbon::parse($shift->end_time)
                ->addMinutes($overtimeMinutes)
                ->format('H:i:s'),
            'late_minutes' => $isLate ? 18 : 0,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $isLate ? 'late' : 'on_time',
        ])->save();
    }

    private function seedAttendanceBreaks(Company $company): void
    {
        $records = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->take(2)
            ->get();

        foreach ($records as $index => $record) {
            $startedAt = Carbon::parse($record->attendance_date->toDateString())->setTime(12, 0);
            $duration = $index === 0 ? 30 : 45;

            AttendanceBreak::query()->updateOrCreate(
                ['attendance_record_id' => $record->id],
                [
                    'started_at' => $startedAt->toDateTimeString(),
                    'ended_at' => $startedAt->copy()->addMinutes($duration)->toDateTimeString(),
                    'duration_minutes' => $duration,
                ],
            );
        }
    }

    private function seedOvertimeRegularizations(Company $company): void
    {
        $approvedAttendance = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->where('overtime_minutes', '>', 0)
            ->orderByDesc('overtime_minutes')
            ->firstOrFail();

        Regularization::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $approvedAttendance->employee_id,
                'attendance_date' => $approvedAttendance->attendance_date,
                'type' => 'overtime',
            ],
            [
                'status' => 'approved',
                'check_in' => $approvedAttendance->check_in,
                'check_out' => $approvedAttendance->check_out,
                'reason' => 'Données démo : heures supplémentaires effectuées et approuvées.',
                'attachment_count' => 0,
                'manager_name' => 'Amina El Idrissi',
                'reviewed_at' => now()->subDays(2),
            ],
        );

        $attendanceWithoutOvertime = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->where('overtime_minutes', 0)
            ->orderByDesc('attendance_date')
            ->get()
            ->unique('employee_id')
            ->take(2)
            ->values();

        foreach ([
            [
                'status' => 'pending',
                'reason' => 'Données démo : demande d’une heure supplémentaire en attente.',
                'requested_minutes' => 60,
                'manager_name' => null,
                'reviewed_at' => null,
            ],
            [
                'status' => 'rejected',
                'reason' => 'Données démo : demande d’heures supplémentaires refusée.',
                'requested_minutes' => 90,
                'manager_name' => 'Amina El Idrissi',
                'reviewed_at' => now()->subDay(),
            ],
        ] as $index => $data) {
            $attendance = $attendanceWithoutOvertime->get($index);
            if ($attendance === null) {
                continue;
            }

            $requestedCheckOut = Carbon::parse($attendance->check_out)
                ->addMinutes($data['requested_minutes'])
                ->format('H:i:s');

            Regularization::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'employee_id' => $attendance->employee_id,
                    'attendance_date' => $attendance->attendance_date,
                    'type' => 'overtime',
                ],
                [
                    'status' => $data['status'],
                    'check_in' => $attendance->check_in,
                    'check_out' => $requestedCheckOut,
                    'reason' => $data['reason'],
                    'attachment_count' => 0,
                    'manager_name' => $data['manager_name'],
                    'reviewed_at' => $data['reviewed_at'],
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedLeaveData(Company $company, Collection $employees): void
    {
        $leaveTypes = collect([
            [
                'code' => 'casual_leave',
                'name' => 'Congé annuel',
                'accrual_rate' => 1.5,
                'allow_carry_forward' => true,
                'max_carry_forward_days' => 5,
            ],
            [
                'code' => 'sick_leave',
                'name' => 'Congé maladie',
                'accrual_rate' => null,
                'allow_carry_forward' => false,
                'max_carry_forward_days' => null,
            ],
        ])->mapWithKeys(fn (array $data) => [
            $data['code'] => LeaveType::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => 'Type de congé pour les données de démonstration.',
                    'enable_automatic_accrual' => $data['accrual_rate'] !== null,
                    'accrual_frequency' => $data['accrual_rate'] !== null ? 'monthly' : null,
                    'accrual_rate' => $data['accrual_rate'],
                    'allow_carry_forward' => $data['allow_carry_forward'],
                    'max_carry_forward_days' => $data['max_carry_forward_days'],
                    'is_active' => true,
                ],
            ),
        ]);

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                LeaveBalance::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => now()->year,
                    ],
                    [
                        'entitled' => $leaveType->code === 'casual_leave' ? 18 : 5,
                        'carried_forward' => $leaveType->code === 'casual_leave' ? 2 : 0,
                        'additional' => 0,
                    ],
                );
            }
        }

        $employee = $this->demoSelfServiceEmployee($employees);
        $pastStart = now()->subMonths(2)->startOfMonth()->toDateString();
        $pastEnd = now()->subMonths(2)->startOfMonth()->addDay()->toDateString();
        LeaveRequest::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'reason' => 'Données démo : congé approuvé',
            ],
            [
                'leave_type' => 'casual_leave',
                'duration_kind' => 'full',
                'start_date' => $pastStart,
                'end_date' => $pastEnd,
                'total_days' => 2,
                'status' => 'approved',
                'manager_name' => 'Amina El Idrissi',
                'reviewed_at' => now()->subMonths(2),
            ],
        );

        LeaveRequest::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'reason' => 'Données démo : demande en attente',
            ],
            [
                'leave_type' => 'casual_leave',
                'duration_kind' => 'full',
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(11)->toDateString(),
                'total_days' => 2,
                'status' => 'pending',
                'manager_name' => null,
                'reviewed_at' => null,
            ],
        );

        $rejectedEmployee = $employees->get(1, $employee);
        LeaveRequest::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $rejectedEmployee->id,
                'reason' => 'Données démo : congé refusé',
            ],
            [
                'leave_type' => 'casual_leave',
                'duration_kind' => 'full',
                'start_date' => now()->addDays(20)->toDateString(),
                'end_date' => now()->addDays(20)->toDateString(),
                'total_days' => 1,
                'status' => 'rejected',
                'manager_name' => 'Amina El Idrissi',
                'reviewed_at' => now()->subDay(),
            ],
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedLeaveBalanceAdjustments(Company $company, Collection $employees): void
    {
        $employee = $this->demoSelfServiceEmployee($employees);
        $leaveType = LeaveType::query()
            ->where('company_id', $company->id)
            ->where('code', 'casual_leave')
            ->firstOrFail();
        $balance = LeaveBalance::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', now()->year)
            ->firstOrFail();
        $balanceBefore = (float) $balance->entitled + (float) $balance->carried_forward;
        $balance->additional = 1;
        $balance->save();

        LeaveBalanceAdjustment::query()->updateOrCreate(
            [
                'leave_balance_id' => $balance->id,
                'reason' => 'Données démo : ajustement manuel de congé',
            ],
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'adjusted_by' => null,
                'year' => now()->year,
                'type' => 'manual_adjustment',
                'days' => 1,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + 1,
            ],
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedCompensatoryOffs(Company $company, Collection $employees): void
    {
        $rows = [
            [
                'employee' => $employees->get(0),
                'worked_days_ago' => 8,
                'status' => 'pending',
                'usage_status' => null,
                'manager_name' => null,
                'reject_reason' => null,
                'reason' => 'Données démo : heures supplémentaires à compenser.',
            ],
            [
                'employee' => $employees->get(1),
                'worked_days_ago' => 15,
                'status' => 'approved',
                'usage_status' => 'available',
                'manager_name' => 'Amina El Idrissi',
                'reject_reason' => null,
                'reason' => 'Données démo : repos compensatoire approuvé et disponible.',
            ],
            [
                'employee' => $employees->get(2),
                'worked_days_ago' => 22,
                'status' => 'approved',
                'usage_status' => 'used',
                'manager_name' => 'Amina El Idrissi',
                'reject_reason' => null,
                'reason' => 'Données démo : repos compensatoire déjà utilisé.',
            ],
            [
                'employee' => $employees->get(3),
                'worked_days_ago' => 29,
                'status' => 'rejected',
                'usage_status' => null,
                'manager_name' => 'Amina El Idrissi',
                'reject_reason' => 'Données démo : justificatif insuffisant.',
                'reason' => 'Données démo : demande de repos compensatoire refusée.',
            ],
        ];

        foreach ($rows as $data) {
            $employee = $data['employee'];
            if ($employee === null) {
                continue;
            }

            $workedDate = now()->subDays($data['worked_days_ago'])->toDateString();
            $compensatoryOff = CompensatoryOff::query()
                ->where('company_id', $company->id)
                ->where('employee_id', $employee->id)
                ->whereDate('worked_date', $workedDate)
                ->first();

            if ($compensatoryOff === null) {
                $compensatoryOff = new CompensatoryOff([
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'worked_date' => $workedDate,
                ]);
            }

            $compensatoryOff->fill([
                'hours_worked' => 3,
                'comp_off_days' => 0.5,
                'expiry_date' => now()->addMonths(3)->toDateString(),
                'status' => $data['status'],
                'usage_status' => $data['usage_status'],
                'reason' => $data['reason'],
                'manager_name' => $data['manager_name'],
                'reject_reason' => $data['reject_reason'],
                'reviewed_at' => $data['status'] === 'pending' ? null : now()->subDays(2),
            ])->save();
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedExpensesAndLoans(Company $company, Collection $employees): void
    {
        $employee = $this->demoSelfServiceEmployee($employees);

        Expense::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'description' => 'Données démo : déplacement client',
            ],
            [
                'type' => 'travel',
                'expense_date' => now()->subDays(3)->toDateString(),
                'amount' => 850,
                'currency' => $company->currency,
                'status' => 'pending',
                'manager_name' => null,
                'reviewed_at' => null,
            ],
        );

        Expense::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'description' => 'Données démo : fournitures de bureau',
            ],
            [
                'type' => 'office_supplies',
                'expense_date' => now()->subDays(15)->toDateString(),
                'amount' => 320,
                'currency' => $company->currency,
                'status' => 'approved',
                'manager_name' => 'Amina El Idrissi',
                'reviewed_at' => now()->subDays(12),
            ],
        );

        Loan::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-LOAN-001'],
            [
                'employee_id' => $employee->id,
                'type' => 'personal',
                'amount' => 6000,
                'currency' => $company->currency,
                'interest_rate' => 12,
                'tenure_months' => 12,
                'emi' => 533.33,
                'outstanding' => 6000,
                'status' => 'pending',
                'purpose' => 'Données démo : avance personnelle',
                'manager_name' => null,
                'reviewed_at' => null,
            ],
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedTrainingAndRecruitment(Company $company, Collection $employees): void
    {
        $training = Training::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Sécurité et bonnes pratiques numériques'],
            [
                'category' => 'compliance',
                'description' => 'Formation de démonstration pour les équipes.',
                'duration' => '2 jours',
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
                'objectives' => ['Protéger les données', 'Appliquer les bonnes pratiques'],
                'status' => 'published',
                'is_active' => true,
            ],
        );

        foreach ($employees->take(2) as $index => $employee) {
            TrainingEnrollment::query()->updateOrCreate(
                ['training_id' => $training->id, 'employee_id' => $employee->id],
                [
                    'company_id' => $company->id,
                    'status' => $index === 0 ? 'in_progress' : 'enrolled',
                    'progress' => $index === 0 ? 50 : 0,
                ],
            );
        }

        $opening = JobOpening::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Développeur·euse PHP',
            ],
            [
                'department' => 'Ingénierie',
                'location' => 'Casablanca',
                'type' => 'full_time',
                'status' => 'open',
                'description' => 'Offre de démonstration pour le module recrutement.',
            ],
        );

        Candidate::query()->updateOrCreate(
            ['company_id' => $company->id, 'email' => 'candidate.demo@example.test'],
            [
                'job_opening_id' => $opening->id,
                'name' => 'Nadia Amrani',
                'phone' => '+212 6 00 00 00 00',
                'position' => 'Développeuse PHP',
                'experience' => '5 ans',
                'source' => 'Site carrière',
                'availability' => 'Sous un mois',
                'summary' => 'Profil de démonstration.',
                'stage' => 'interview',
                'last_action' => 'Entretien technique planifié',
                'applied_date' => now()->subDays(12)->toDateString(),
            ],
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedDocumentsAndEquipment(Company $company, Collection $employees): void
    {
        $employee = $this->demoSelfServiceEmployee($employees);

        EmployeeDocument::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'reference' => 'DEMO-ID-'.$employee->matricule,
            ],
            [
                'name' => 'Pièce d’identité — document démo',
                'category' => 'national_id',
                'issue_date' => now()->subYears(2)->toDateString(),
                'expiry_date' => now()->addYears(3)->toDateString(),
                'status' => 'verified',
                'manager_name' => 'Amina El Idrissi',
                'reviewed_at' => now()->subDays(5),
            ],
        );

        DocumentRequest::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'type' => 'salary_letter',
                'purpose' => 'Données démo : demande d’attestation de salaire',
            ],
            [
                'needed_by' => now()->addDays(7)->toDateString(),
                'status' => 'pending',
            ],
        );

        $equipment = Equipment::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Ordinateur portable de démonstration'],
            [
                'category' => 'laptop',
                'description' => 'Matériel fictif pour tester les demandes et attributions.',
                'stock' => 4,
                'brand' => 'Lenovo',
                'model' => 'ThinkPad E14',
                'specifications' => ['memory' => '16 Go', 'storage' => '512 Go SSD'],
                'is_active' => true,
            ],
        );

        EquipmentRequest::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'equipment_id' => $equipment->id,
                'reason' => 'Données démo : demande de matériel',
            ],
            [
                'quantity' => 1,
                'priority' => 'medium',
                'status' => 'pending',
                'expected_date' => now()->addDays(10)->toDateString(),
            ],
        );

        EquipmentAssignment::query()->updateOrCreate(
            ['company_id' => $company->id, 'serial_number' => 'DEMO-LAPTOP-001'],
            [
                'equipment_id' => $equipment->id,
                'employee_id' => $employee->id,
                'equipment_request_id' => null,
                'status' => 'assigned',
                'assigned_date' => now()->subMonths(2)->toDateString(),
            ],
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedPayroll(Collection $employees): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');
        $payrolls = app(PayrollService::class);

        foreach ($employees as $employee) {
            $payrolls->generate($employee, $period);
        }
    }
}
