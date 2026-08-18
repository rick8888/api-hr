<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\TrainingEnrollmentService;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreTrainingEnrollmentRequest;
use App\Http\Requests\Training\UpdateTrainingEnrollmentStatusRequest;
use App\Http\Resources\TrainingEnrollmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TrainingEnrollmentController extends Controller
{
    public function __construct(private readonly TrainingEnrollmentService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'mine' => $request->query('mine'),
            'status' => $request->query('status'),
            'training_id' => $request->query('training_id'),
            'employee_id' => $request->query('employee_id'),
        ];

        return TrainingEnrollmentResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreTrainingEnrollmentRequest $request): JsonResponse
    {
        $row = $this->service->enrollForActor(
            $request->user(),
            (int) $request->validated('training_id'),
        );

        return (new TrainingEnrollmentResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, TrainingEnrollment $training_enrollment): TrainingEnrollmentResource
    {
        return new TrainingEnrollmentResource(
            $this->service->findForActor($request->user(), $training_enrollment)
        );
    }

    public function updateStatus(
        UpdateTrainingEnrollmentStatusRequest $request,
        TrainingEnrollment $training_enrollment,
    ): TrainingEnrollmentResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $training_enrollment,
            (string) $validated['status'],
            isset($validated['progress']) ? (int) $validated['progress'] : null,
            $validated['score'] ?? null,
            $request->file('certificate'),
        );

        return new TrainingEnrollmentResource($updated);
    }

    public function destroy(Request $request, TrainingEnrollment $training_enrollment): JsonResponse
    {
        $this->service->cancelForActor($request->user(), $training_enrollment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
