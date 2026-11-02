<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use SoftDeletes;

    public const STATUSES = ['on_time', 'late', 'half_day', 'leave', 'absent'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'shift_id',
        'site_id',
        'attendance_date',
        'check_in',
        'check_out',
        'late_minutes',
        'early_out_minutes',
        'overtime_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'late_minutes' => 'integer',
            'early_out_minutes' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }
}
