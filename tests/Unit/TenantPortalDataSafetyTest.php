<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\DTO\Company\CompanyData;
use App\Application\Services\AdminDashboardService;
use App\Application\Services\AuditLogger;
use App\Application\Services\CompanyService;
use App\Application\Services\CompanyUserService;
use App\Application\Services\EmployeeProfileLinker;
use App\Application\Services\EmployeeService;
use App\Application\Services\SelfServiceProfileService;
use App\Application\Services\TenantCompanyProfileService;
use App\Application\Services\TenantContactUserService;
use App\Application\Services\TenantPlanQuotaService;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Infrastructure\Repositories\Eloquent\EloquentCompanyRepository;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantPortalDataSafetyTest extends TestCase
{
    private string $originalDefaultConnection;

    /** @var array<string, mixed> */
    private array $originalSqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteConnection = config('database.connections.sqlite');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('currency');
            $table->string('locale')->default('fr_FR');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('currency')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('country_id');
            $table->string('matricule')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->string('national_id')->nullable();
            $table->string('social_security_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('contract_type')->default('CDI');
            $table->string('payment_method')->default('bank');
            $table->string('bank_account')->nullable();
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_employees')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('subscription_plan_id');
            $table->string('status')->default('active');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('event');
            $table->string('context')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite' => $this->originalSqliteConnection,
        ]);

        parent::tearDown();
    }

    public function test_profile_update_preserves_unedited_company_settings(): void
    {
        $country = Country::query()->create([
            'code' => 'COD',
            'name' => 'République démocratique du Congo',
            'currency' => 'CDF',
        ]);
        $settings = [
            'plan' => 'growth',
            'subdomain' => 'acme',
            'website' => 'https://old.example',
            'vat_number' => 'OLD-VAT',
            'payroll' => ['currency' => 'CDF', 'tax_profile' => 'drc'],
            'custom_setting' => ['enabled' => true],
        ];
        $companyId = DB::table('companies')->insertGetId([
            'country_id' => $country->id,
            'code' => 'ACME',
            'name' => 'Acme',
            'tax_id' => 'OLD-VAT',
            'phone' => '+243000000000',
            'currency' => 'CDF',
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actor = new User(['role' => 'tenant', 'company_id' => $companyId]);
        $service = new TenantCompanyProfileService(new CompanyService(
            new EloquentCompanyRepository,
            new AuditLogger,
            new TenantContactUserService,
        ));

        $service->updateForActor($actor, [
            'name' => 'Acme Updated',
            'phone' => null,
            'tax_id' => null,
            'website' => null,
            'address' => ['city' => 'Kinshasa'],
        ]);

        $updatedCompany = Company::query()->findOrFail($companyId);
        $updatedSettings = $updatedCompany->settings;
        $this->assertSame($settings['payroll'], $updatedSettings['payroll']);
        $this->assertSame($settings['custom_setting'], $updatedSettings['custom_setting']);
        $this->assertSame('growth', $updatedSettings['plan']);
        $this->assertArrayNotHasKey('website', $updatedSettings);
        $this->assertArrayNotHasKey('vat_number', $updatedSettings);
        $this->assertSame('Kinshasa', $updatedSettings['address']['city']);
        $this->assertNull($updatedCompany->phone);
        $this->assertNull($updatedCompany->tax_id);
    }

    public function test_admin_dashboard_scopes_team_manager_attendance_and_leave_data(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->date('attendance_date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('status', 30);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('leave_type', 40);
            $table->string('duration_kind', 10)->default('full');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1)->default(1);
            $table->string('status', 20);
            $table->text('reason')->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->date('date');
            $table->boolean('is_active')->default(true);
            $table->boolean('half_day')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('candidates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('job_openings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        [$company, $country] = $this->createCompanyWithQuota(maxUsers: 10, maxEmployees: 10);
        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-MANAGER',
            'first_name' => 'Team',
            'last_name' => 'Manager',
            'email' => 'manager@example.test',
            'is_active' => true,
        ]);
        $directReport = Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-REPORT',
            'first_name' => 'Direct',
            'last_name' => 'Report',
            'metadata' => ['reporting_manager_id' => $manager->id],
            'is_active' => true,
        ]);
        $otherEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-OTHER',
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'is_active' => true,
        ]);
        $teamManager = User::query()->create([
            'name' => 'Team Manager',
            'email' => 'manager@example.test',
            'password' => 'secret-password',
            'role' => 'team_manager',
            'company_id' => $company->id,
        ]);
        DB::table('roles')->insert([
            [
                'name' => 'Team Manager',
                'slug' => 'team_manager',
                'permissions' => json_encode(['dashboard.view', 'employees.view', 'attendance.manage', 'leave.manage'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'permissions' => json_encode(['dashboard.view', 'employees.view', 'employees.manage', 'organization.manage'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $today = today();
        $yesterday = $today->copy()->subDay()->toDateString();
        $todayString = $today->toDateString();
        $teamLeaveDate = $today->copy()->addDays(2)->toDateString();
        $otherLeaveDate = $today->copy()->addDays(3)->toDateString();
        foreach ([
            [$directReport->id, $yesterday, 'on_time'],
            [$otherEmployee->id, $yesterday, 'on_time'],
            [$directReport->id, $todayString, 'on_time'],
            [$otherEmployee->id, $todayString, 'late'],
        ] as [$employeeId, $date, $status]) {
            DB::table('attendance_records')->insert([
                'company_id' => $company->id,
                'employee_id' => $employeeId,
                'attendance_date' => $date,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            [$directReport->id, $teamLeaveDate],
            [$otherEmployee->id, $otherLeaveDate],
        ] as [$employeeId, $leaveDate]) {
            DB::table('leave_requests')->insert([
                'company_id' => $company->id,
                'employee_id' => $employeeId,
                'leave_type' => 'casual_leave',
                'start_date' => $leaveDate,
                'end_date' => $leaveDate,
                'status' => 'pending',
                'reason' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $service = app(AdminDashboardService::class);
        $managerDashboard = $service->showForActor($teamManager);
        $adminDashboard = $service->showForActor($admin);
        $managerWeeklyChart = $service->showForActor($teamManager, 'week')['attendance_chart'];
        $managerMonthlyChart = $service->showForActor($teamManager, 'month')['attendance_chart'];

        $managerScheduleNames = collect($managerDashboard['schedule'])
            ->flatMap(fn (array $day) => $day['events'])
            ->pluck('title')
            ->all();
        $adminScheduleNames = collect($adminDashboard['schedule'])
            ->flatMap(fn (array $day) => $day['events'])
            ->pluck('title')
            ->all();

        $managerKpis = collect($managerDashboard['kpis'])->keyBy('id');
        $adminKpis = collect($adminDashboard['kpis'])->keyBy('id');

        $this->assertSame(1, $managerKpis['attendance']['value']);
        $this->assertSame('0%', $managerKpis['attendance']['change']);
        $this->assertFalse($managerKpis->has('applicants'));
        $this->assertFalse($managerKpis->has('open_positions'));
        $this->assertSame(100, $managerDashboard['attendance_chart'][0]['present']);
        $this->assertSame(0, $managerDashboard['attendance_chart'][0]['late']);
        $this->assertSame(0, collect($managerWeeklyChart)->max('late'));
        $this->assertSame(0, collect($managerMonthlyChart)->max('late'));
        $this->assertSame(33, $adminDashboard['attendance_chart'][0]['late']);
        $this->assertSame([$teamLeaveDate], $managerDashboard['schedule_dates']);
        $this->assertSame(['Direct Report'], $managerScheduleNames);
        $this->assertTrue($adminKpis->has('applicants'));
        $this->assertTrue($adminKpis->has('open_positions'));
        $this->assertContains($otherLeaveDate, $adminDashboard['schedule_dates']);
        $this->assertContains('Other Employee', $adminScheduleNames);
    }

    public function test_missing_tenant_password_is_rejected_instead_of_using_a_default(): void
    {
        $company = new Company([
            'name' => 'Acme',
            'settings' => ['primary_contact' => ['email' => 'tenant@example.test']],
        ]);
        $company->id = 7;

        try {
            (new TenantContactUserService)->syncForCompany($company);
            $this->fail('A tenant account without a password should not be created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_contact.password', $exception->errors());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_company_creation_rolls_back_when_tenant_password_is_missing(): void
    {
        $country = Country::query()->create([
            'code' => 'COD',
            'name' => 'République démocratique du Congo',
            'currency' => 'CDF',
        ]);
        $service = new CompanyService(
            new EloquentCompanyRepository,
            new AuditLogger,
            new TenantContactUserService,
        );

        try {
            $service->create(CompanyData::fromArray([
                'country_id' => $country->id,
                'name' => 'Acme',
                'code' => 'ACME',
                'settings' => [
                    'primary_contact' => ['email' => 'tenant@example.test'],
                ],
            ]));
            $this->fail('Company creation should fail without a tenant password.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_contact.password', $exception->errors());
        }

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tenant_cannot_create_an_admin_over_the_user_quota(): void
    {
        [$company] = $this->createCompanyWithQuota(maxUsers: 1, maxEmployees: 5);
        $actor = User::query()->create([
            'name' => 'Tenant',
            'email' => 'tenant@example.test',
            'password' => 'secret-password',
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);
        $quotas = new TenantPlanQuotaService;
        $service = new CompanyUserService(new EmployeeProfileLinker($quotas), $quotas);

        try {
            $service->createAdmin($actor, [
                'name' => 'HR Admin',
                'email' => 'admin@example.test',
                'password' => 'secret-password',
            ]);
            $this->fail('Creating an admin should fail when the user quota is full.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quota.users', $exception->errors());
        }

        $this->assertSame(1, User::query()->where('company_id', $company->id)->count());
    }

    public function test_tenant_cannot_create_an_admin_that_exceeds_employee_quota(): void
    {
        [$company] = $this->createCompanyWithQuota(maxUsers: 5, maxEmployees: 1);
        $actor = User::query()->create([
            'name' => 'Tenant',
            'email' => 'tenant@example.test',
            'password' => 'secret-password',
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $company->country_id,
            'matricule' => 'EMP-EXISTING',
            'first_name' => 'Existing',
            'last_name' => 'Employee',
            'is_active' => true,
        ]);
        $quotas = new TenantPlanQuotaService;
        $service = new CompanyUserService(new EmployeeProfileLinker($quotas), $quotas);

        try {
            $service->createAdmin($actor, [
                'name' => 'HR Admin',
                'email' => 'admin@example.test',
                'password' => 'secret-password',
            ]);
            $this->fail('Creating an admin should fail when its employee profile would exceed the employee quota.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quota.employees', $exception->errors());
        }

        $this->assertSame(1, User::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, Employee::query()->where('company_id', $company->id)->count());
    }

    public function test_employee_creation_is_rejected_when_the_active_employee_quota_is_full(): void
    {
        [$company, $country] = $this->createCompanyWithQuota(maxUsers: 5, maxEmployees: 1);
        $actor = new User(['role' => 'admin', 'company_id' => $company->id]);
        Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-EXISTING',
            'first_name' => 'Existing',
            'last_name' => 'Employee',
            'is_active' => true,
        ]);

        try {
            app(EmployeeService::class)->createForActor($actor, [
                'matricule' => 'EMP-NEW',
                'first_name' => 'New',
                'last_name' => 'Employee',
            ]);
            $this->fail('Creating an employee should fail when the employee quota is full.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quota.employees', $exception->errors());
        }

        $this->assertSame(1, Employee::query()->where('company_id', $company->id)->count());
    }

    public function test_bulk_employee_activation_cannot_exceed_employee_quota(): void
    {
        [$company, $country] = $this->createCompanyWithQuota(maxUsers: 5, maxEmployees: 1);
        $actor = new User(['role' => 'admin', 'company_id' => $company->id]);
        $employees = collect(['EMP-INACTIVE-1', 'EMP-INACTIVE-2'])->map(
            fn (string $matricule): Employee => Employee::query()->create([
                'company_id' => $company->id,
                'country_id' => $country->id,
                'matricule' => $matricule,
                'first_name' => 'Inactive',
                'last_name' => 'Employee',
                'is_active' => false,
            ])
        );

        try {
            app(EmployeeService::class)->bulkSetStatusForActor(
                $actor,
                $employees->pluck('id')->all(),
                'active',
            );
            $this->fail('Bulk activation should fail when it exceeds the employee quota.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quota.employees', $exception->errors());
        }

        $this->assertSame(0, Employee::query()->where('company_id', $company->id)->where('is_active', true)->count());
    }

    public function test_automatic_employee_profile_creation_cannot_bypass_employee_quota(): void
    {
        [$company, $country] = $this->createCompanyWithQuota(maxUsers: 5, maxEmployees: 1);
        Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-EXISTING',
            'first_name' => 'Existing',
            'last_name' => 'Employee',
            'is_active' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.test',
            'password' => 'secret-password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        try {
            (new EmployeeProfileLinker(new TenantPlanQuotaService))->createFromUser($user);
            $this->fail('Auto-provisioning an employee profile should enforce the employee quota.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quota.employees', $exception->errors());
        }

        $this->assertSame(1, Employee::query()->where('company_id', $company->id)->count());
    }

    public function test_inactive_employee_cannot_log_in(): void
    {
        [$company, $country] = $this->createCompanyWithQuota(maxUsers: 5, maxEmployees: 5);
        $user = User::query()->create([
            'name' => 'Inactive Employee',
            'email' => 'inactive@example.test',
            'password' => 'secret-password',
            'role' => 'employe',
            'company_id' => $company->id,
        ]);
        Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-INACTIVE',
            'first_name' => 'Inactive',
            'last_name' => 'Employee',
            'email' => $user->email,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Votre compte est inactif. Contactez les ressources humaines.');
    }

    public function test_employee_profile_update_can_clear_optional_contact_fields(): void
    {
        [$company, $country] = $this->createCompanyWithQuota(maxUsers: 5, maxEmployees: 5);
        $user = User::query()->create([
            'name' => 'Employee Name',
            'email' => 'employee@example.test',
            'password' => 'secret-password',
            'role' => 'employe',
            'company_id' => $company->id,
        ]);
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'matricule' => 'EMP-001',
            'first_name' => 'Employee',
            'last_name' => 'Name',
            'email' => $user->email,
            'phone' => '+243000000000',
            'date_of_birth' => '1990-01-01',
            'is_active' => true,
        ]);
        $quotas = new TenantPlanQuotaService;
        $service = new SelfServiceProfileService(
            new EmployeeProfileLinker($quotas),
            new AuditLogger,
        );

        $service->updateForActor($user, [
            'phone' => null,
            'date_of_birth' => null,
        ]);

        $employee->refresh();
        $this->assertNull($employee->phone);
        $this->assertNull($employee->date_of_birth);
    }

    private function createCompanyWithQuota(int $maxUsers, int $maxEmployees): array
    {
        $country = Country::query()->create([
            'code' => 'COD',
            'name' => 'République démocratique du Congo',
            'currency' => 'CDF',
        ]);
        $plan = SubscriptionPlan::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'max_users' => $maxUsers,
            'max_employees' => $maxEmployees,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $companyId = DB::table('companies')->insertGetId([
            'country_id' => $country->id,
            'code' => 'ACME',
            'name' => 'Acme',
            'currency' => 'CDF',
            'settings' => json_encode(['plan' => $plan->code], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [Company::query()->findOrFail($companyId), $country];
    }
}
