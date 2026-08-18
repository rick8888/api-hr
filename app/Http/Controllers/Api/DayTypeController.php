<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\DayTypeData;
use App\Application\Services\DayTypeService;
use App\Domain\Platform\Models\DayType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreDayTypeRequest;
use App\Http\Requests\Platform\ToggleDayTypeStatusRequest;
use App\Http\Requests\Platform\UpdateDayTypeRequest;
use App\Http\Resources\DayTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DayTypeController extends Controller
{
    public function __construct(private readonly DayTypeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $types = $onlyActive ? $this->service->listActive() : $this->service->list();

        return DayTypeResource::collection($types);
    }

    public function store(StoreDayTypeRequest $request): JsonResponse
    {
        $type = $this->service->create(DayTypeData::fromArray($request->validated()));

        return (new DayTypeResource($type))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DayType $day_type): DayTypeResource
    {
        return new DayTypeResource($day_type);
    }

    public function update(
        UpdateDayTypeRequest $request,
        DayType $day_type,
    ): DayTypeResource {
        $updated = $this->service->update($day_type, $request->validated());

        return new DayTypeResource($updated);
    }

    public function toggleStatus(
        ToggleDayTypeStatusRequest $request,
        DayType $day_type,
    ): DayTypeResource {
        $updated = $this->service->toggleStatus(
            $day_type,
            (bool) $request->validated('is_active'),
        );

        return new DayTypeResource($updated);
    }

    public function destroy(Request $request, DayType $day_type): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($day_type);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
