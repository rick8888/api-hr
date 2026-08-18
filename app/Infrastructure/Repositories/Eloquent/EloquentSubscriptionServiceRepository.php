<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Subscription\Models\SubscriptionService;
use App\Domain\Subscription\Repositories\SubscriptionServiceRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentSubscriptionServiceRepository implements SubscriptionServiceRepositoryInterface
{
    public function allActive(): Collection
    {
        return SubscriptionService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }
}
