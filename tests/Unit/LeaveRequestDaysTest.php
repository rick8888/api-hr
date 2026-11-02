<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Leave\Models\LeaveRequest;
use App\Infrastructure\Repositories\Eloquent\EloquentLeaveBalanceRepository;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LeaveRequestDaysTest extends TestCase
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

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('leave_type');
            $table->string('duration_kind');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1);
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_expected_days_match_the_selected_date_range_and_duration(): void
    {
        $this->assertSame(4.0, LeaveRequest::expectedTotalDays('2025-12-30', '2026-01-02', 'full'));
        $this->assertSame(0.5, LeaveRequest::expectedTotalDays('2026-05-04', '2026-05-04', 'half'));
    }

    public function test_leave_days_are_allocated_to_each_year_they_overlap(): void
    {
        DB::table('leave_requests')->insert([
            'company_id' => 1,
            'employee_id' => 9,
            'leave_type' => 'annual_leave',
            'duration_kind' => 'full',
            'start_date' => '2025-12-30',
            'end_date' => '2026-01-02',
            'total_days' => 4,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $repository = new EloquentLeaveBalanceRepository;

        $this->assertSame(
            ['9|ANNUAL_LEAVE' => 2.0],
            $repository->usedDaysForCompanyAndYear(1, 2025),
        );
        $this->assertSame(
            ['9|ANNUAL_LEAVE' => 2.0],
            $repository->usedDaysForCompanyAndYear(1, 2026),
        );
    }

    public function test_half_day_is_counted_only_in_its_year(): void
    {
        $request = new LeaveRequest([
            'duration_kind' => 'half',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
        ]);

        $this->assertSame(
            0.5,
            $request->daysWithinPeriod(
                Carbon::create(2026, 1, 1)->startOfDay(),
                Carbon::create(2026, 12, 31)->startOfDay(),
            ),
        );
        $this->assertSame(
            0.0,
            $request->daysWithinPeriod(
                Carbon::create(2025, 1, 1)->startOfDay(),
                Carbon::create(2025, 12, 31)->startOfDay(),
            ),
        );
    }
}
