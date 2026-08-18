<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Domain\Company\Models\Country;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceObligation extends Model
{
    use HasFactory;

    public const TYPE_FISCAL = 'fiscal';

    public const TYPE_SOCIAL = 'social';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_QUARTERLY = 'quarterly';

    public const FREQUENCY_ANNUAL = 'annual';

    public const REF_PREVIOUS_MONTH = 'previous_month';

    public const REF_PREVIOUS_QUARTER = 'previous_quarter';

    public const REF_PREVIOUS_YEAR = 'previous_year';

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'description',
        'type',
        'authority',
        'frequency',
        'reference_period',
        'due_rule',
        'deadline_day_of_month',
        'grace_days',
        'effective_from',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'due_rule' => 'array',
            'settings' => 'array',
            'deadline_day_of_month' => 'integer',
            'grace_days' => 'integer',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(Declaration::class);
    }
}
