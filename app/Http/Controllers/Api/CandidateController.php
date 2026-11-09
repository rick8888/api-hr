<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Recruitment\CandidateData;
use App\Application\Services\CandidateService;
use App\Domain\Recruitment\Models\Candidate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\HireCandidateRequest;
use App\Http\Requests\Recruitment\StoreCandidateRequest;
use App\Http\Requests\Recruitment\UpdateCandidateRequest;
use App\Http\Requests\Recruitment\UpdateCandidateStageRequest;
use App\Http\Resources\CandidateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CandidateController extends Controller
{
    public function __construct(private readonly CandidateService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'stage' => $request->query('stage') ?? $request->query('status'),
            'job_opening_id' => $request->query('job_opening_id') ?? $request->query('jobOpeningId'),
            'limit' => $request->query('limit'),
        ];

        return CandidateResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $row = $this->service->createForActor(
            $request->user(),
            CandidateData::fromArray($request->validated()),
        );

        return (new CandidateResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Candidate $candidate): CandidateResource
    {
        return new CandidateResource(
            $this->service->findForActor($request->user(), $candidate)
        );
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): CandidateResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $candidate,
            $request->validated(),
        );

        return new CandidateResource($updated);
    }

    public function updateStage(
        UpdateCandidateStageRequest $request,
        Candidate $candidate,
    ): CandidateResource {
        $updated = $this->service->updateStageForActor(
            $request->user(),
            $candidate,
            (string) $request->validated('stage'),
        );

        return new CandidateResource($updated);
    }

    public function hire(HireCandidateRequest $request, Candidate $candidate): CandidateResource
    {
        return new CandidateResource(
            $this->service->hireForActor(
                $request->user(),
                $candidate,
                $request->validated(),
            )
        );
    }

    public function destroy(Request $request, Candidate $candidate): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $candidate);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
