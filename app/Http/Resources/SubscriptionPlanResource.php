<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Subscription\Models\SubscriptionPlan */
class SubscriptionPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'price_monthly' => (float) $this->price_monthly,
            'currency' => $this->currency,
            'currency_prices' => $this->currency_prices ?? [],
            'max_users' => $this->max_users,
            'max_employees' => $this->max_employees,
            'storage_gb' => $this->storage_gb,
            'is_active' => (bool) $this->is_active,
            'is_highlighted' => (bool) $this->is_highlighted,
            'is_free' => (bool) $this->is_free,
            'sort_order' => $this->sort_order,
            'included_services' => $this->included_services ?? [],
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
