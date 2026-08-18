<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Organization\DesignationData;
use App\Application\Services\DesignationService;
use App\Domain\Organization\Models\Designation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDesignationRequest;
use App\Http\Requests\Organization\UpdateDesignationRequest;
use App\Http\Resources\DesignationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DesignationController extends Controller
{
    public function __construct(private readonly DesignationService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DesignationResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function store(StoreDesignationRequest $request): JsonResponse
    {
        $designation = $this->service->createForActor(
            $request->user(),
            DesignationData::fromArray($request->validated())
        );

        return (new DesignationResource($designation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Designation $designation): DesignationResource
    {
        return new DesignationResource(
            $this->service->findForActor($request->user(), $designation)
        );
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): DesignationResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $designation,
            $request->validated()
        );

        return new DesignationResource($updated);
    }

    public function destroy(Request $request, Designation $designation): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $designation);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Designation $designation): DesignationResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $designation);

        return new DesignationResource($updated);
    }
}
