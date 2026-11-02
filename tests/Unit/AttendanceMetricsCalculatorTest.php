<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\AttendanceMetricsCalculator;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Shift;
use App\Http\Resources\AttendanceRecordResource;
use Illuminate\Http\Request;
use Tests\TestCase;

final class AttendanceMetricsCalculatorTest extends TestCase
{
    public function test_it_calculates_lateness_and_overtime_for_an_overnight_shift(): void
    {
        $shift = new Shift([
            'start_time' => '22:00',
            'end_time' => '06:00',
        ]);

        $result = app(AttendanceMetricsCalculator::class)->calculate([
            'check_in' => '22:15',
            'check_out' => '06:30',
            'status' => 'on_time',
        ], $shift);

        $this->assertSame(15, $result['late_minutes']);
        $this->assertSame(0, $result['early_out_minutes']);
        $this->assertSame(30, $result['overtime_minutes']);
        $this->assertSame('late', $result['status']);
    }

    public function test_it_calculates_early_departure_for_an_overnight_shift(): void
    {
        $shift = new Shift([
            'start_time' => '22:00',
            'end_time' => '06:00',
        ]);

        $result = app(AttendanceMetricsCalculator::class)->calculate([
            'check_in' => '22:00',
            'check_out' => '05:45',
            'status' => 'on_time',
        ], $shift);

        $this->assertSame(0, $result['late_minutes']);
        $this->assertSame(15, $result['early_out_minutes']);
        $this->assertSame(0, $result['overtime_minutes']);
    }

    public function test_it_preserves_regular_shift_metrics(): void
    {
        $shift = new Shift([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $result = app(AttendanceMetricsCalculator::class)->calculate([
            'check_in' => '08:10',
            'check_out' => '17:30',
            'status' => 'on_time',
        ], $shift);

        $this->assertSame(10, $result['late_minutes']);
        $this->assertSame(0, $result['early_out_minutes']);
        $this->assertSame(30, $result['overtime_minutes']);
        $this->assertSame('late', $result['status']);
    }

    public function test_resource_counts_worked_minutes_across_an_overnight_shift(): void
    {
        $shift = new Shift([
            'start_time' => '22:00',
            'end_time' => '06:00',
        ]);
        $record = new AttendanceRecord([
            'check_in' => '22:00',
            'check_out' => '06:30',
            'status' => 'late',
        ]);
        $record->setRelation('shift', $shift);

        $resource = new AttendanceRecordResource($record);
        $data = $resource->toArray(Request::create('/'));

        $this->assertSame(510, $data['working_minutes']);
    }
}
