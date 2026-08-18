<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Leave\LeaveTypeData;
use App\Application\Services\LeaveTypeService;
use App\Domain\Leave\Models\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveTypeRequest;
use App\Http\Requests\Leave\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LeaveTypeController extends Controller
{
    public function __construct(private readonly LeaveTypeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return LeaveTypeResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $leaveType = $this->service->createForActor(
            $request->user(),
            LeaveTypeData::fromArray($request->validated())
        );

        return (new LeaveTypeResource($leaveType))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, LeaveType $leave_type): LeaveTypeResource
    {
        return new LeaveTypeResource(
            $this->service->findForActor($request->user(), $leave_type)
        );
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leave_type): LeaveTypeResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $leave_type,
            $request->validated()
        );

        return new LeaveTypeResource($updated);
    }

    public function destroy(Request $request, LeaveType $leave_type): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $leave_type);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, LeaveType $leave_type): LeaveTypeResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $leave_type);

        return new LeaveTypeResource($updated);
    }
}
