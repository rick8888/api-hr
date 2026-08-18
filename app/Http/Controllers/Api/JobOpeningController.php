<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Recruitment\JobOpeningData;
use App\Application\Services\JobOpeningService;
use App\Domain\Recruitment\Models\JobOpening;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreJobOpeningRequest;
use App\Http\Requests\Recruitment\UpdateJobOpeningRequest;
use App\Http\Resources\JobOpeningResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class JobOpeningController extends Controller
{
    public function __construct(private readonly JobOpeningService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'department' => $request->query('department'),
        ];

        if (! empty($filters['status'])) {
            $filters['status'] = JobOpeningData::normalizeStatus((string) $filters['status']);
        }
        if (! empty($filters['type'])) {
            $filters['type'] = JobOpeningData::normalizeType((string) $filters['type']);
        }

        return JobOpeningResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreJobOpeningRequest $request): JsonResponse
    {
        $row = $this->service->createForActor(
            $request->user(),
            JobOpeningData::fromArray($request->validated()),
        );

        return (new JobOpeningResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, JobOpening $job_opening): JobOpeningResource
    {
        return new JobOpeningResource(
            $this->service->findForActor($request->user(), $job_opening)
        );
    }

    public function update(UpdateJobOpeningRequest $request, JobOpening $job_opening): JobOpeningResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $job_opening,
            $request->validated(),
        );

        return new JobOpeningResource($updated);
    }

    public function destroy(Request $request, JobOpening $job_opening): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $job_opening);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
