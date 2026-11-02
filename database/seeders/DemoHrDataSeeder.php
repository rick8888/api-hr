<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Services\PayrollService;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Expense\Models\Expense;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Models\LeaveType;
use App\Domain\Loan\Models\Loan;
use App\Domain\Organization\Models\Department;
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

        $employees = collect([Employee::query()->where('matricule', 'EMP-0001')->firstOrFail()]);
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
        $this->seedLeaveData($company, $employees);
        $this->seedExpensesAndLoans($company, $employees);
        $this->seedTrainingAndRecruitment($company, $employees);
        $this->seedPayroll($employees);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedAttendance(Company $company, Collection $employees, Site $site, Shift $shift): void
    {
        $date = Carbon::today();
        $workingDaysSeeded = 0;

        while ($workingDaysSeeded < 10) {
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

                    AttendanceRecord::query()->updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'employee_id' => $employee->id,
                            'attendance_date' => $date->copy(),
                        ],
                        [
                            'shift_id' => $shift->id,
                            'site_id' => $site->id,
                            'check_in' => $isLate ? '09:18:00' : '08:55:00',
                            'check_out' => Carbon::parse($shift->end_time)
                                ->addMinutes($overtimeMinutes)
                                ->format('H:i:s'),
                            'late_minutes' => $isLate ? 18 : 0,
                            'overtime_minutes' => $overtimeMinutes,
                            'status' => $isLate ? 'late' : 'on_time',
                        ],
                    );
                }

                $workingDaysSeeded++;
            }

            $date->subDay();
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

        $employee = $employees->first();
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
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function seedExpensesAndLoans(Company $company, Collection $employees): void
    {
        $employee = $employees->first();

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
    private function seedPayroll(Collection $employees): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');
        $payrolls = app(PayrollService::class);

        foreach ($employees as $employee) {
            $payrolls->generate($employee, $period);
        }
    }
}
