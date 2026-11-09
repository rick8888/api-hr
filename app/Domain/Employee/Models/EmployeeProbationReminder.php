<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProbationReminder extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'recipient_user_id',
        'period_end_date',
        'days_before',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'period_end_date' => 'date',
            'sent_at' => 'datetime',
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
