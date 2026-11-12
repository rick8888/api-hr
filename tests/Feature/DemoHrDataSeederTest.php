<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Attendance\Models\AttendanceBreak;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOnboardingTask;
use App\Domain\Employee\Models\PerformanceReview;
use App\Domain\Employee\Models\SelfServiceNote;
use App\Domain\Leave\Models\CompensatoryOff;
use App\Domain\Organization\Models\Designation;
use App\Domain\Organization\Models\Holiday;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Recruitment\Models\Candidate;
use App\Models\User;
use Carbon\CarbonPeriod;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoHrDataSeeder;
use Database\Seeders\DemoWorkflowDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoHrDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_seed_provides_complete_repeatable_hr_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();
        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $previousMonthWorkingDays = collect(CarbonPeriod::create(
            $previousMonthStart,
            $previousMonthEnd,
        ))->filter(static fn ($date) => $date->isWeekday())->count();
        $currentMonthElapsedWorkingDays = collect(CarbonPeriod::create(
            now()->startOfMonth(),
            now(),
        ))->filter(static fn ($date) => $date->isWeekday())->count();
        $currentMonthSeededDays = min(5, $currentMonthElapsedWorkingDays);

        $this->assertSame(7, Employee::query()->where('company_id', $company->id)->count());
        $this->assertSame(
            6 * ($previousMonthWorkingDays + $currentMonthSeededDays),
            AttendanceRecord::query()->where('company_id', $company->id)->count(),
        );
        $this->assertSame(
            6 * $previousMonthWorkingDays,
            AttendanceRecord::query()
                ->where('company_id', $company->id)
                ->whereDate('attendance_date', '>=', $previousMonthStart->toDateString())
                ->whereDate('attendance_date', '<=', $previousMonthEnd->toDateString())
                ->count(),
        );
        $this->assertSame(
            6 * $currentMonthSeededDays,
            AttendanceRecord::query()
                ->where('company_id', $company->id)
                ->whereDate('attendance_date', '>=', now()->startOfMonth()->toDateString())
                ->whereDate('attendance_date', '<=', now()->toDateString())
                ->count(),
        );
        $this->assertSame(2, AttendanceBreak::query()
            ->whereHas('attendanceRecord', fn ($query) => $query->where('company_id', $company->id))
            ->count());
        $this->assertSame(4, Designation::query()->where('company_id', $company->id)->count());
        $this->assertSame(2, Holiday::query()->where('company_id', $company->id)->count());
        $this->assertSame(4, CompensatoryOff::query()->where('company_id', $company->id)->count());
        $this->assertSame(6, Payroll::query()->where('company_id', $company->id)->count());
        $onboardingEmployee = Employee::query()->where('matricule', 'EMP-0006')->firstOrFail();
        $this->assertSame('onboarding', $onboardingEmployee->metadata['employment_status']);
        $this->assertSame(5, EmployeeOnboardingTask::query()
            ->where('employee_id', $onboardingEmployee->id)
            ->count());
        $this->assertSame(2, EmployeeOnboardingTask::query()
            ->where('employee_id', $onboardingEmployee->id)
            ->where('status', EmployeeOnboardingTask::STATUS_COMPLETED)
            ->count());
        $this->assertSame(1, PerformanceReview::query()
            ->where('company_id', $company->id)
            ->where('status', PerformanceReview::STATUS_SUBMITTED)
            ->count());
        $this->assertSame(1, PerformanceReview::query()
            ->where('company_id', $company->id)
            ->where('status', PerformanceReview::STATUS_DRAFT)
            ->count());
        $this->assertSame(4, Candidate::query()->where('company_id', $company->id)->count());
        $this->assertSame(2, SelfServiceNote::query()
            ->where('user_id', User::query()->where('email', 'employee@bravorh.com')->value('id'))
            ->count());
        $this->assertSame(
            390,
            (int) AttendanceRecord::query()
                ->where('company_id', $company->id)
                ->where('attendance_date', '>=', now()->subMonthNoOverflow()->startOfMonth()->toDateString())
                ->where('attendance_date', '<=', now()->subMonthNoOverflow()->endOfMonth()->toDateString())
                ->sum('overtime_minutes'),
        );
        $payroll = Payroll::query()
            ->where('company_id', $company->id)
            ->where('employee_id', Employee::query()->where('matricule', 'EMP-0001')->value('id'))
            ->where('period', now()->subMonthNoOverflow()->format('Y-m'))
            ->firstOrFail();
        $this->assertSame(2.0, (float) $payroll->snapshot['attendance']['overtime_hours']);
        $brooklyn = Employee::query()->where('email', 'employee@bravorh.com')->firstOrFail();
        $employeeUser = User::query()->where('email', 'employee@bravorh.com')->firstOrFail();
        $brooklynAttendance = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $brooklyn->id)
            ->whereDate('attendance_date', '>=', $previousMonthStart->toDateString())
            ->whereDate('attendance_date', '<=', $previousMonthEnd->toDateString())
            ->count();
        $this->assertSame($previousMonthWorkingDays, $brooklynAttendance);
        $this->assertDatabaseHas('leave_requests', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'status' => 'approved',
            'reason' => 'Données démo : congé approuvé',
        ]);
        $this->assertDatabaseHas('expenses', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'description' => 'Données démo : déplacement client',
        ]);
        $this->assertDatabaseHas('loans', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'code' => 'DEMO-LOAN-001',
        ]);
        $this->assertDatabaseHas('employee_documents', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'reference' => 'DEMO-ID-'.$brooklyn->matricule,
        ]);
        $this->assertDatabaseHas('document_requests', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'purpose' => 'Données démo : demande d’attestation de salaire',
        ]);
        $this->assertDatabaseHas('equipment_requests', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'reason' => 'Données démo : demande de matériel',
        ]);
        $this->assertDatabaseHas('equipment_assignments', [
            'company_id' => $company->id,
            'employee_id' => $brooklyn->id,
            'serial_number' => 'DEMO-LAPTOP-001',
        ]);
        $brooklynPayroll = Payroll::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $brooklyn->id)
            ->where('period', now()->subMonthNoOverflow()->format('Y-m'))
            ->firstOrFail();
        $this->assertSame((float) $previousMonthWorkingDays, (float) $brooklynPayroll->snapshot['attendance']['days_worked']);
        $this->assertSame(0.0, (float) $brooklynPayroll->snapshot['attendance']['absent_days']);
        $this->actingAs($employeeUser, 'sanctum')
            ->getJson('/api/v1/self-service/payslips/'.$brooklynPayroll->id)
            ->assertOk()
            ->assertJsonPath('data.attendance.working_days', $previousMonthWorkingDays)
            ->assertJsonPath('data.attendance.worked_days', $previousMonthWorkingDays)
            ->assertJsonPath('data.attendance.absent_days', 0)
            ->assertJsonPath('data.attendance.overtime_days', 1);
        $this->getJson('/api/v1/expenses?mine=1')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/loans?mine=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/employee-documents?mine=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/document-requests?mine=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/equipment-requests?mine=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/equipment-assignments?mine=1')->assertOk()->assertJsonCount(1, 'data');
        $admin = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $this->assertSame($company->id, $admin->company_id);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/admin')
            ->assertOk()
            ->assertJsonPath('data.kpis.0.id', 'employees')
            ->assertJsonPath('data.kpis.0.value', 6);
        Storage::fake('public');
        $avatarResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/employees/'.$brooklyn->id.'/avatar', [
                'avatar' => UploadedFile::fake()->image('profile.png'),
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $brooklyn->id)
            ->assertJsonStructure(['data' => ['avatar_url']]);
        $avatarPath = $brooklyn->fresh()->avatar_path;
        Storage::disk('public')->assertExists($avatarPath);
        $avatarResponse->assertJsonPath('data.avatar_url', 'http://localhost/storage/'.$avatarPath);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/employees/'.$brooklyn->id.'/avatar', [
                'avatar' => UploadedFile::fake()->image('replacement.png'),
            ])
            ->assertOk();
        $replacementPath = $brooklyn->fresh()->avatar_path;
        $this->assertNotSame($avatarPath, $replacementPath);
        Storage::disk('public')->assertMissing($avatarPath);
        Storage::disk('public')->assertExists($replacementPath);
        $this->actingAs($employeeUser, 'sanctum')
            ->postJson('/api/v1/employees/'.$brooklyn->id.'/avatar', [
                'avatar' => UploadedFile::fake()->image('unauthorized.png'),
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('leave_requests', [
            'company_id' => $company->id,
            'status' => 'pending',
            'reason' => 'Données démo : demande en attente',
        ]);
        $this->assertDatabaseHas('expenses', [
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('loans', [
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('candidates', [
            'company_id' => $company->id,
            'email' => 'candidate.demo@example.test',
            'stage' => 'interview',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'employee@bravorh.com',
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('leave_requests', [
            'company_id' => $company->id,
            'status' => 'rejected',
            'reason' => 'Données démo : congé refusé',
        ]);
        $this->assertDatabaseHas('leave_balance_adjustments', [
            'company_id' => $company->id,
            'reason' => 'Données démo : ajustement manuel de congé',
            'days' => 1,
        ]);
        $this->assertDatabaseHas('compensatory_offs', [
            'company_id' => $company->id,
            'status' => 'approved',
            'usage_status' => 'available',
        ]);
        $this->assertDatabaseHas('compensatory_offs', [
            'company_id' => $company->id,
            'status' => 'approved',
            'usage_status' => 'used',
        ]);
        $this->assertDatabaseHas('compensatory_offs', [
            'company_id' => $company->id,
            'status' => 'rejected',
        ]);

        $this->seed(DemoHrDataSeeder::class);
        $this->seed(DemoWorkflowDataSeeder::class);

        $this->assertSame(7, Employee::query()->where('company_id', $company->id)->count());
        $this->assertSame(
            6 * ($previousMonthWorkingDays + $currentMonthSeededDays),
            AttendanceRecord::query()->where('company_id', $company->id)->count(),
        );
        $this->assertSame(2, AttendanceBreak::query()
            ->whereHas('attendanceRecord', fn ($query) => $query->where('company_id', $company->id))
            ->count());
        $this->assertSame(4, Designation::query()->where('company_id', $company->id)->count());
        $this->assertSame(2, Holiday::query()->where('company_id', $company->id)->count());
        $this->assertSame(4, CompensatoryOff::query()->where('company_id', $company->id)->count());
        $this->assertSame(6, Payroll::query()->where('company_id', $company->id)->count());
        $this->assertSame(5, EmployeeOnboardingTask::query()
            ->where('employee_id', $onboardingEmployee->id)
            ->count());
        $this->assertSame(2, SelfServiceNote::query()
            ->where('user_id', $employeeUser->id)
            ->count());
    }
}
