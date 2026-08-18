<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\AttendanceTypeData;
use App\Application\Services\AttendanceTypeService;
use App\Domain\Platform\Models\AttendanceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreAttendanceTypeRequest;
use App\Http\Requests\Platform\ToggleAttendanceTypeStatusRequest;
use App\Http\Requests\Platform\UpdateAttendanceTypeRequest;
use App\Http\Resources\AttendanceTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AttendanceTypeController extends Controller
{
    public function __construct(private readonly AttendanceTypeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $types = $onlyActive ? $this->service->listActive() : $this->service->list();

        return AttendanceTypeResource::collection($types);
    }

    public function store(StoreAttendanceTypeRequest $request): JsonResponse
    {
        $type = $this->service->create(AttendanceTypeData::fromArray($request->validated()));

        return (new AttendanceTypeResource($type))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(AttendanceType $attendance_type): AttendanceTypeResource
    {
        return new AttendanceTypeResource($attendance_type);
    }

    public function update(
        UpdateAttendanceTypeRequest $request,
        AttendanceType $attendance_type,
    ): AttendanceTypeResource {
        $updated = $this->service->update($attendance_type, $request->validated());

        return new AttendanceTypeResource($updated);
    }

    public function toggleStatus(
        ToggleAttendanceTypeStatusRequest $request,
        AttendanceType $attendance_type,
    ): AttendanceTypeResource {
        $updated = $this->service->toggleStatus(
            $attendance_type,
            (bool) $request->validated('is_active'),
        );

        return new AttendanceTypeResource($updated);
    }

    public function destroy(Request $request, AttendanceType $attendance_type): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($attendance_type);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
