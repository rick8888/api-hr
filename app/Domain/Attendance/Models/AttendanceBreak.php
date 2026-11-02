<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceBreak extends Model
{
    protected $fillable = [
        'attendance_record_id',
        'started_at',
        'ended_at',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function finish(Carbon $endedAt): int
    {
        if ($endedAt->lt($this->started_at)) {
            $endedAt = $this->started_at->copy();
        }
        $this->ended_at = $endedAt;
        $this->duration_minutes = (int) $this->started_at->diffInMinutes($endedAt);
        $this->save();

        return $this->duration_minutes;
    }
}
