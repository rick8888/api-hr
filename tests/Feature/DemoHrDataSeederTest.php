<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoHrDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoHrDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_seed_provides_complete_repeatable_hr_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('code', 'ACME')->firstOrFail();

        $this->assertSame(5, Employee::query()->where('company_id', $company->id)->count());
        $this->assertSame(50, AttendanceRecord::query()->where('company_id', $company->id)->count());
        $this->assertSame(5, Payroll::query()->where('company_id', $company->id)->count());
        $this->assertSame(
            390,
            AttendanceRecord::query()
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

        $this->seed(DemoHrDataSeeder::class);

        $this->assertSame(5, Employee::query()->where('company_id', $company->id)->count());
        $this->assertSame(50, AttendanceRecord::query()->where('company_id', $company->id)->count());
        $this->assertSame(5, Payroll::query()->where('company_id', $company->id)->count());
    }
}
