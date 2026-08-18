<?php

declare(strict_types=1);

namespace App\Domain\Equipment\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentRequest extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'company_id',
        'equipment_id',
        'employee_id',
        'quantity',
        'priority',
        'status',
        'reason',
        'expected_date',
        'manager_name',
        'reject_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expected_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
