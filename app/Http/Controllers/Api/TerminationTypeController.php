<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\TerminationTypeData;
use App\Application\Services\TerminationTypeService;
use App\Domain\Platform\Models\TerminationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTerminationTypeRequest;
use App\Http\Requests\Platform\ToggleTerminationTypeStatusRequest;
use App\Http\Requests\Platform\UpdateTerminationTypeRequest;
use App\Http\Resources\TerminationTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TerminationTypeController extends Controller
{
    public function __construct(private readonly TerminationTypeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $types = $onlyActive ? $this->service->listActive() : $this->service->list();

        return TerminationTypeResource::collection($types);
    }

    public function store(StoreTerminationTypeRequest $request): JsonResponse
    {
        $type = $this->service->create(TerminationTypeData::fromArray($request->validated()));

        return (new TerminationTypeResource($type))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TerminationType $termination_type): TerminationTypeResource
    {
        return new TerminationTypeResource($termination_type);
    }

    public function update(
        UpdateTerminationTypeRequest $request,
        TerminationType $termination_type,
    ): TerminationTypeResource {
        $updated = $this->service->update($termination_type, $request->validated());

        return new TerminationTypeResource($updated);
    }

    public function toggleStatus(
        ToggleTerminationTypeStatusRequest $request,
        TerminationType $termination_type,
    ): TerminationTypeResource {
        $updated = $this->service->toggleStatus(
            $termination_type,
            (bool) $request->validated('is_active'),
        );

        return new TerminationTypeResource($updated);
    }

    public function destroy(Request $request, TerminationType $termination_type): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($termination_type);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
