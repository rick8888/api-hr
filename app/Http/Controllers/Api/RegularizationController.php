<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Attendance\RegularizationData;
use App\Application\Services\RegularizationService;
use App\Domain\Attendance\Models\Regularization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreRegularizationRequest;
use App\Http\Requests\Attendance\UpdateRegularizationRequest;
use App\Http\Requests\Attendance\UpdateRegularizationStatusRequest;
use App\Http\Resources\RegularizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RegularizationController extends Controller
{
    public function __construct(private readonly RegularizationService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'employee_id' => $request->query('employee_id'),
            'mine' => $request->query('mine'),
        ];

        return RegularizationResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreRegularizationRequest $request): JsonResponse
    {
        $regularization = $this->service->createForActor(
            $request->user(),
            RegularizationData::fromArray($request->validated())
        );

        return (new RegularizationResource($regularization))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Regularization $regularization): RegularizationResource
    {
        return new RegularizationResource(
            $this->service->findForActor($request->user(), $regularization)
        );
    }

    public function update(
        UpdateRegularizationRequest $request,
        Regularization $regularization,
    ): RegularizationResource {
        $updated = $this->service->updateForActor(
            $request->user(),
            $regularization,
            $request->validated()
        );

        return new RegularizationResource($updated);
    }

    public function updateStatus(
        UpdateRegularizationStatusRequest $request,
        Regularization $regularization,
    ): RegularizationResource {
        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $regularization,
            (string) $request->validated('status'),
        );

        return new RegularizationResource($updated);
    }

    public function destroy(Request $request, Regularization $regularization): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $regularization);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
