<?php

declare(strict_types=1);

namespace App\Domain\Equipment\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentAssignment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['assigned', 'returned'];

    protected $fillable = [
        'company_id',
        'equipment_id',
        'employee_id',
        'equipment_request_id',
        'serial_number',
        'status',
        'assigned_date',
        'issue_note',
        'issue_reported_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'issue_reported_at' => 'datetime',
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

    public function request(): BelongsTo
    {
        return $this->belongsTo(EquipmentRequest::class, 'equipment_request_id');
    }
}
