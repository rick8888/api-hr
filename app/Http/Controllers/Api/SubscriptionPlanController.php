<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Subscription\SubscriptionPlanData;
use App\Application\Services\SubscriptionPlanService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionPlanRequest;
use App\Http\Requests\Subscription\ToggleSubscriptionPlanStatusRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionPlanRequest;
use App\Http\Resources\SubscriptionPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionPlanController extends Controller
{
    public function __construct(private readonly SubscriptionPlanService $service)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return SubscriptionPlanResource::collection($this->service->list());
    }

    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = $this->service->create(SubscriptionPlanData::fromArray($request->validated()));

        return (new SubscriptionPlanResource($plan))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(SubscriptionPlan $subscription_plan): SubscriptionPlanResource
    {
        $plan = $this->service->find($subscription_plan->id) ?? $subscription_plan;

        return new SubscriptionPlanResource($plan);
    }

    public function update(
        UpdateSubscriptionPlanRequest $request,
        SubscriptionPlan $subscription_plan,
    ): SubscriptionPlanResource {
        $updated = $this->service->updateFromValidated($subscription_plan, $request->validated());

        return new SubscriptionPlanResource($updated);
    }

    public function toggleStatus(
        ToggleSubscriptionPlanStatusRequest $request,
        SubscriptionPlan $subscription_plan,
    ): SubscriptionPlanResource {
        $updated = $this->service->toggleStatus(
            $subscription_plan,
            (bool) $request->validated('is_active'),
        );

        return new SubscriptionPlanResource($updated);
    }

    public function destroy(SubscriptionPlan $subscription_plan): JsonResponse
    {
        $this->service->delete($subscription_plan);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
