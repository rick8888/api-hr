<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PerformanceReviewService;
use App\Domain\Employee\Models\PerformanceObjective;
use App\Domain\Employee\Models\PerformanceReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\StorePerformanceReviewRequest;
use App\Http\Requests\Performance\UpdatePerformanceObjectiveProgressRequest;
use App\Http\Requests\Performance\UpdatePerformanceReviewRequest;
use App\Http\Requests\Performance\UpdatePerformanceReviewStatusRequest;
use App\Http\Resources\PerformanceObjectiveResource;
use App\Http\Resources\PerformanceReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PerformanceReviewController extends Controller
{
    public function __construct(private readonly PerformanceReviewService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PerformanceReviewResource::collection(
            $this->service->listForActor(
                $request->user(),
                $request->validate([
                    'employee_id' => ['sometimes', 'integer'],
                    'status' => ['sometimes', 'in:draft,submitted'],
                    'search' => ['sometimes', 'string', 'max:255'],
                ])
            )
        );
    }

    public function store(StorePerformanceReviewRequest $request): JsonResponse
    {
        $review = $this->service->createForActor($request->user(), $request->validated());

        return (new PerformanceReviewResource($review))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePerformanceReviewRequest $request, PerformanceReview $performance_review): PerformanceReviewResource
    {
        return new PerformanceReviewResource(
            $this->service->updateForActor(
                $request->user(),
                $performance_review,
                $request->validated(),
            )
        );
    }

    public function updateStatus(
        UpdatePerformanceReviewStatusRequest $request,
        PerformanceReview $performance_review,
    ): PerformanceReviewResource {
        return new PerformanceReviewResource(
            $this->service->submitForActor($request->user(), $performance_review)
        );
    }

    public function updateObjectiveProgress(
        UpdatePerformanceObjectiveProgressRequest $request,
        PerformanceReview $performance_review,
        PerformanceObjective $objective,
    ): PerformanceObjectiveResource {
        return new PerformanceObjectiveResource(
            $this->service->updateObjectiveProgressForActor(
                $request->user(),
                $performance_review,
                $objective,
                $request->validated(),
            )
        );
    }

    public function destroy(Request $request, PerformanceReview $performance_review): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $performance_review);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
