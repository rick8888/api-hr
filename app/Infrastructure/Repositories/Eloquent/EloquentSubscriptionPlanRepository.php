<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Repositories\SubscriptionPlanRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentSubscriptionPlanRepository implements SubscriptionPlanRepositoryInterface
{
    public function all(): Collection
    {
        return SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()->find($id);
    }

    public function findByCode(string $code): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('code', $code)->first();
    }

    public function create(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create($data);
    }

    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->fill($data)->save();

        return $plan->refresh();
    }

    public function delete(SubscriptionPlan $plan): bool
    {
        return (bool) $plan->delete();
    }

    public function clearHighlightExcept(?int $exceptPlanId = null): void
    {
        SubscriptionPlan::query()
            ->when($exceptPlanId !== null, fn ($q) => $q->where('id', '!=', $exceptPlanId))
            ->update(['is_highlighted' => false]);
    }

    public function nextSortOrder(): int
    {
        $max = SubscriptionPlan::query()->max('sort_order');

        return ((int) $max) + 1;
    }
}
