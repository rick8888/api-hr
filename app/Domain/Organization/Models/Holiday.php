<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'date',
        'type',
        'category',
        'description',
        'color',
        'applicable_to',
        'recurring',
        'optional_holiday',
        'restricted_holiday',
        'half_day',
        'visible_to_employees',
        'send_notification',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recurring' => 'boolean',
            'optional_holiday' => 'boolean',
            'restricted_holiday' => 'boolean',
            'half_day' => 'boolean',
            'visible_to_employees' => 'boolean',
            'send_notification' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
