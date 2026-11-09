<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'tagline',
        'price_monthly',
        'currency',
        'currency_prices',
        'max_users',
        'max_employees',
        'storage_gb',
        'is_active',
        'is_highlighted',
        'is_free',
        'sort_order',
        'included_services',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'currency_prices' => 'array',
            'max_users' => 'integer',
            'max_employees' => 'integer',
            'storage_gb' => 'integer',
            'is_active' => 'boolean',
            'is_highlighted' => 'boolean',
            'is_free' => 'boolean',
            'sort_order' => 'integer',
            'included_services' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
