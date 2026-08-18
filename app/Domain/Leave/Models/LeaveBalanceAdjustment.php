<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceAdjustment extends Model
{
    protected $fillable = [
        'leave_balance_id',
        'company_id',
        'employee_id',
        'leave_type_id',
        'adjusted_by',
        'year',
        'type',
        'days',
        'balance_before',
        'balance_after',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'days' => 'float',
            'balance_before' => 'float',
            'balance_after' => 'float',
        ];
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
