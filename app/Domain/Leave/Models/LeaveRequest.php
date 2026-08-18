<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    public const LEAVE_TYPES = [
        'casual_leave',
        'emergency_leave',
        'sick_leave',
        'voting_leave',
        'work_from_home',
        'maternity_leave',
        'paternity_leave',
    ];

    public const DURATION_KINDS = ['full', 'half'];

    public const STATUSES = ['pending', 'approved', 'cancelled'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type',
        'duration_kind',
        'start_date',
        'end_date',
        'total_days',
        'status',
        'reason',
        'emergency_contact',
        'traveling_abroad',
        'document',
        'manager_name',
        'cancel_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'float',
            'emergency_contact' => 'boolean',
            'traveling_abroad' => 'boolean',
            'reviewed_at' => 'datetime',
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
}
