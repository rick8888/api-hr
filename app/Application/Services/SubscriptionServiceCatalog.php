<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Subscription\Repositories\SubscriptionServiceRepositoryInterface;
use Illuminate\Support\Collection;

final class SubscriptionServiceCatalog
{
    public function __construct(
        private readonly SubscriptionServiceRepositoryInterface $repository,
    ) {
    }

    public function list(): Collection
    {
        return $this->repository->allActive();
    }
}
