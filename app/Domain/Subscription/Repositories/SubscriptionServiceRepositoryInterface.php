<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Repositories;

use Illuminate\Support\Collection;

interface SubscriptionServiceRepositoryInterface
{
    /** @return Collection<int, \App\Domain\Subscription\Models\SubscriptionService> */
    public function allActive(): Collection;
}
