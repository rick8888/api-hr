<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\PayrollAttendanceCalculator;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PayrollAttendanceCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_out_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status');
            $table->softDeletes();
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('status');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('leave_type');
            $table->string('duration_kind')->nullable();
            $table->softDeletes();
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->boolean('recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hire_mid_month_uses_full_month_working_days_for_proration(): void
    {
        $summary = $this->summarizeForEmploymentDates('2026-01-16', null);

        $this->assertSame(22.0, $summary['working_days']);
        $this->assertSame(11.0, $summary['payable_days']);
        $this->assertSame(100.0, $summary['daily_rate']);
        $this->assertSame(1100.0, $summary['payable_salary']);
    }

    public function test_termination_mid_month_uses_full_month_working_days_for_proration(): void
    {
        $summary = $this->summarizeForEmploymentDates(null, '2026-01-15');

        $this->assertSame(22.0, $summary['working_days']);
        $this->assertSame(11.0, $summary['payable_days']);
        $this->assertSame(100.0, $summary['daily_rate']);
        $this->assertSame(1100.0, $summary['payable_salary']);
    }

    public function test_full_month_employment_keeps_the_full_monthly_salary(): void
    {
        $summary = $this->summarizeForEmploymentDates(null, null);

        $this->assertSame(22.0, $summary['working_days']);
        $this->assertSame(22.0, $summary['payable_days']);
        $this->assertSame(2200.0, $summary['payable_salary']);
    }

    public function test_summary_includes_late_early_overtime_day_and_holiday_counts(): void
    {
        Carbon::setTestNow('2026-01-31');
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'late',
            'late_minutes' => 12,
            'early_out_minutes' => 8,
            'overtime_minutes' => 60,
        ]);
        DB::table('holidays')->insert([
            'company_id' => $company->id,
            'date' => '2026-01-06',
            'recurring' => false,
            'is_active' => true,
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-05',
            '2026-01-06',
            200.0,
        );

        $this->assertSame(1.0, $summary['late_days']);
        $this->assertSame(1.0, $summary['early_checkout_days']);
        $this->assertSame(1.0, $summary['overtime_days']);
        $this->assertSame(1.0, $summary['holidays']);
    }

    public function test_preview_treats_unrecorded_workdays_as_pending_and_payable(): void
    {
        Carbon::setTestNow('2025-12-31');
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-01',
            '2026-01-31',
            2200.0,
            assumeUnrecordedDaysPayable: true,
        );

        $this->assertSame(22.0, $summary['working_days']);
        $this->assertSame(22.0, $summary['pending_attendance_days']);
        $this->assertSame(0.0, $summary['absent_days']);
        $this->assertSame(0.0, $summary['no_show_days']);
        $this->assertSame(22.0, $summary['payable_days']);
        $this->assertSame(2200.0, $summary['payable_salary']);
    }

    public function test_preview_keeps_explicit_absences_distinct_from_unrecorded_days(): void
    {
        Carbon::setTestNow('2026-01-15');
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-16',
            'status' => 'absent',
            'overtime_minutes' => 0,
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-01',
            '2026-01-31',
            2200.0,
            assumeUnrecordedDaysPayable: true,
        );

        $this->assertSame(11.0, $summary['absent_days']);
        $this->assertSame(1.0, $summary['recorded_absent_days']);
        $this->assertSame(11.0, $summary['pending_attendance_days']);
        $this->assertSame(10.0, $summary['no_show_days']);
        $this->assertSame(11.0, $summary['payable_days']);
        $this->assertSame(1100.0, $summary['payable_salary']);
    }

    public function test_approved_paid_leave_overrides_a_recorded_absence(): void
    {
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'absent',
            'overtime_minutes' => 0,
        ]);
        DB::table('leave_requests')->insert([
            'employee_id' => $employee->id,
            'status' => 'approved',
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-05',
            'leave_type' => 'annual_leave',
            'duration_kind' => 'full',
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-05',
            '2026-01-05',
            100.0,
        );

        $this->assertSame(1.0, $summary['leave_days']);
        $this->assertSame(0.0, $summary['recorded_absent_days']);
        $this->assertSame(0.0, $summary['absent_days']);
        $this->assertSame(1.0, $summary['payable_days']);
    }

    public function test_attendance_leave_without_an_approved_request_is_not_paid_leave(): void
    {
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'leave',
            'overtime_minutes' => 0,
        ]);
        DB::table('leave_requests')->insert([
            'employee_id' => $employee->id,
            'status' => 'pending',
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-05',
            'leave_type' => 'annual_leave',
            'duration_kind' => 'full',
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-05',
            '2026-01-05',
            100.0,
        );

        $this->assertSame(0.0, $summary['leave_days']);
        $this->assertSame(1.0, $summary['recorded_absent_days']);
        $this->assertSame(0.0, $summary['payable_days']);
    }

    public function test_half_day_approved_leave_and_absence_only_deduct_the_uncovered_half(): void
    {
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'absent',
            'overtime_minutes' => 0,
        ]);
        DB::table('leave_requests')->insert([
            'employee_id' => $employee->id,
            'status' => 'approved',
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-05',
            'leave_type' => 'annual_leave',
            'duration_kind' => 'half',
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-05',
            '2026-01-05',
            100.0,
        );

        $this->assertSame(0.5, $summary['leave_days']);
        $this->assertSame(0.5, $summary['recorded_absent_days']);
        $this->assertSame(0.5, $summary['absent_days']);
        $this->assertSame(0.5, $summary['payable_days']);
    }

    public function test_half_day_attendance_without_approved_leave_deducts_the_unworked_half(): void
    {
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('attendance_records')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'half_day',
            'overtime_minutes' => 0,
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-05',
            '2026-01-05',
            100.0,
        );

        $this->assertSame(0.5, $summary['days_worked']);
        $this->assertSame(0.5, $summary['recorded_absent_days']);
        $this->assertSame(0.5, $summary['payable_days']);
    }

    public function test_half_day_approved_leave_leaves_unrecorded_time_pending_in_preview(): void
    {
        Carbon::setTestNow('2026-01-04');
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        DB::table('leave_requests')->insert([
            'employee_id' => $employee->id,
            'status' => 'approved',
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-05',
            'leave_type' => 'annual_leave',
            'duration_kind' => 'half',
        ]);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-05',
            '2026-01-05',
            100.0,
            assumeUnrecordedDaysPayable: true,
        );

        $this->assertSame(0.5, $summary['leave_days']);
        $this->assertSame(0.5, $summary['pending_attendance_days']);
        $this->assertSame(1.0, $summary['payable_days']);
    }

    public function test_payroll_calculation_keeps_existing_missing_attendance_behavior(): void
    {
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->setRelation('company', $company);

        $summary = app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-01',
            '2026-01-31',
            2200.0,
        );

        $this->assertSame(0.0, $summary['pending_attendance_days']);
        $this->assertSame(0.0, $summary['recorded_absent_days']);
        $this->assertSame(22.0, $summary['no_show_days']);
        $this->assertSame(22.0, $summary['absent_days']);
        $this->assertSame(0.0, $summary['payable_salary']);
    }

    /** @return array<string, float> */
    private function summarizeForEmploymentDates(?string $hiredAt, ?string $terminatedAt): array
    {
        $company = new Company;
        $company->id = 1;
        $company->settings = [];

        $employee = new Employee;
        $employee->id = 1;
        $employee->hired_at = $hiredAt;
        $employee->terminated_at = $terminatedAt;
        $employee->setRelation('company', $company);

        $from = Carbon::parse($hiredAt ?? '2026-01-01');
        $to = Carbon::parse($terminatedAt ?? '2026-01-31');
        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($day->isWeekday()) {
                DB::table('attendance_records')->insert([
                    'employee_id' => $employee->id,
                    'attendance_date' => $day->toDateString(),
                    'status' => 'on_time',
                    'overtime_minutes' => 0,
                ]);
            }
        }

        return app(PayrollAttendanceCalculator::class)->summarize(
            $employee,
            '2026-01-01',
            '2026-01-31',
            2200.0,
        );
    }
}
