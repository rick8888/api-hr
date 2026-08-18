<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\RegularizationTypeData;
use App\Application\Services\RegularizationTypeService;
use App\Domain\Platform\Models\RegularizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreRegularizationTypeRequest;
use App\Http\Requests\Platform\ToggleRegularizationTypeStatusRequest;
use App\Http\Requests\Platform\UpdateRegularizationTypeRequest;
use App\Http\Resources\RegularizationTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RegularizationTypeController extends Controller
{
    public function __construct(private readonly RegularizationTypeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $types = $onlyActive ? $this->service->listActive() : $this->service->list();

        return RegularizationTypeResource::collection($types);
    }

    public function store(StoreRegularizationTypeRequest $request): JsonResponse
    {
        $type = $this->service->create(RegularizationTypeData::fromArray($request->validated()));

        return (new RegularizationTypeResource($type))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(RegularizationType $regularization_type): RegularizationTypeResource
    {
        return new RegularizationTypeResource($regularization_type);
    }

    public function update(
        UpdateRegularizationTypeRequest $request,
        RegularizationType $regularization_type,
    ): RegularizationTypeResource {
        $updated = $this->service->update($regularization_type, $request->validated());

        return new RegularizationTypeResource($updated);
    }

    public function toggleStatus(
        ToggleRegularizationTypeStatusRequest $request,
        RegularizationType $regularization_type,
    ): RegularizationTypeResource {
        $updated = $this->service->toggleStatus(
            $regularization_type,
            (bool) $request->validated('is_active'),
        );

        return new RegularizationTypeResource($updated);
    }

    public function destroy(Request $request, RegularizationType $regularization_type): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($regularization_type);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
