<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'year',
        'entitled',
        'carried_forward',
        'additional',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled' => 'float',
            'carried_forward' => 'float',
            'additional' => 'float',
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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class);
    }
}
