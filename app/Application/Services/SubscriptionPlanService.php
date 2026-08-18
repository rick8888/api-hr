<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Subscription\SubscriptionPlanData;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Repositories\SubscriptionPlanRepositoryInterface;
use Illuminate\Support\Collection;

final class SubscriptionPlanService
{
    public function __construct(
        private readonly SubscriptionPlanRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, SubscriptionPlan> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): ?SubscriptionPlan
    {
        return $this->repository->find($id);
    }

    public function create(SubscriptionPlanData $data): SubscriptionPlan
    {
        if ($this->repository->findByCode($data->toModelArray()['code'])) {
            throw new \InvalidArgumentException('Ce code de plan existe déjà.');
        }

        $payload = $data->toModelArray();
        $payload['sort_order'] = $data->sortOrder ?? $this->repository->nextSortOrder();

        if ($data->isHighlighted) {
            $this->repository->clearHighlightExcept(null);
        }

        $plan = $this->repository->create($payload);
        $this->audit->log('subscription_plan.created', $plan, ['code' => $plan->code]);

        return $plan;
    }

    public function update(SubscriptionPlan $plan, SubscriptionPlanData $data): SubscriptionPlan
    {
        $payload = $data->toModelArray();
        unset($payload['code']);

        if ($data->isHighlighted) {
            $this->repository->clearHighlightExcept($plan->id);
        }

        $updated = $this->repository->update($plan, $payload);
        $this->audit->log('subscription_plan.updated', $updated, ['code' => $updated->code]);

        return $updated;
    }

    /** @param array<string, mixed> $validated */
    public function updateFromValidated(SubscriptionPlan $plan, array $validated): SubscriptionPlan
    {
        $current = array_merge([
            'code' => $plan->code,
            'name' => $plan->name,
            'tagline' => $plan->tagline,
            'price_monthly' => $plan->price_monthly,
            'currency' => $plan->currency,
            'max_users' => $plan->max_users,
            'max_employees' => $plan->max_employees,
            'storage_gb' => $plan->storage_gb,
            'is_active' => $plan->is_active,
            'is_highlighted' => $plan->is_highlighted,
            'sort_order' => $plan->sort_order,
            'included_services' => $plan->included_services ?? [],
        ], $validated);

        return $this->update($plan, SubscriptionPlanData::fromArray($current));
    }

    public function toggleStatus(SubscriptionPlan $plan, bool $isActive): SubscriptionPlan
    {
        $updated = $this->repository->update($plan, ['is_active' => $isActive]);
        $this->audit->log('subscription_plan.status_changed', $updated, [
            'code' => $updated->code,
            'is_active' => $isActive,
        ]);

        return $updated;
    }

    public function delete(SubscriptionPlan $plan): bool
    {
        $this->audit->log('subscription_plan.deleted', $plan, ['code' => $plan->code]);

        return $this->repository->delete($plan);
    }
}
