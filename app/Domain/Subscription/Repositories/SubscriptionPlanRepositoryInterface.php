<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Repositories;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Support\Collection;

interface SubscriptionPlanRepositoryInterface
{
    /** @return Collection<int, SubscriptionPlan> */
    public function all(): Collection;

    public function find(int $id): ?SubscriptionPlan;

    public function findByCode(string $code): ?SubscriptionPlan;

    /** @param array<string, mixed> $data */
    public function create(array $data): SubscriptionPlan;

    /** @param array<string, mixed> $data */
    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan;

    public function delete(SubscriptionPlan $plan): bool;

    public function clearHighlightExcept(?int $exceptPlanId = null): void;

    public function nextSortOrder(): int;
}
