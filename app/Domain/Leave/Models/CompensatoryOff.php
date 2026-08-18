<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompensatoryOff extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const USAGE_STATUSES = ['available', 'used', 'expired'];

    protected $table = 'compensatory_offs';

    protected $fillable = [
        'company_id',
        'employee_id',
        'worked_date',
        'hours_worked',
        'comp_off_days',
        'expiry_date',
        'status',
        'usage_status',
        'reason',
        'manager_name',
        'reject_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'worked_date' => 'date',
            'expiry_date' => 'date',
            'hours_worked' => 'float',
            'comp_off_days' => 'float',
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
