<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Attendance\Models\AttendanceBreak;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Http\Resources\AttendanceRecordResource;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AttendanceBreakTest extends TestCase
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

        Schema::create('attendance_breaks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('attendance_record_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_finishing_a_break_persists_its_duration(): void
    {
        $break = AttendanceBreak::query()->create([
            'attendance_record_id' => 17,
            'started_at' => '2026-10-30 12:00:00',
        ]);

        $duration = $break->finish(Carbon::parse('2026-10-30 12:25:00'));

        $this->assertSame(25, $duration);
        $this->assertSame(25, $break->fresh()->duration_minutes);
        $this->assertSame('2026-10-30 12:25:00', $break->fresh()->ended_at->toDateTimeString());
    }

    public function test_attendance_resource_exposes_break_history_and_active_break_duration(): void
    {
        Carbon::setTestNow('2026-10-30 12:20:00');
        $record = new AttendanceRecord(['check_in' => '08:00', 'check_out' => null]);
        $break = new AttendanceBreak([
            'started_at' => '2026-10-30 12:10:00',
            'ended_at' => null,
            'duration_minutes' => 0,
        ]);
        $completedBreak = new AttendanceBreak([
            'started_at' => '2026-10-30 10:00:00',
            'ended_at' => '2026-10-30 10:25:00',
            'duration_minutes' => 25,
        ]);
        $record->setRelation('breaks', collect([$completedBreak, $break]));

        $data = (new AttendanceRecordResource($record))->toArray(Request::create('/'));

        $this->assertSame(35, $data['break_minutes']);
        $this->assertSame(25, $data['recorded_break_minutes']);
        $this->assertTrue($data['is_on_break']);
        $this->assertSame(
            Carbon::parse('2026-10-30 12:10:00')->toIso8601String(),
            $data['active_break_started_at'],
        );
        $this->assertSame(2, count($data['breaks']));
        $this->assertSame(25, $data['breaks'][0]['duration_minutes']);
        $this->assertSame(10, $data['breaks'][1]['duration_minutes']);
    }
}
