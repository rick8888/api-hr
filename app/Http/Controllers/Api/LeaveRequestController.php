<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Leave\LeaveRequestData;
use App\Application\Services\LeaveRequestService;
use App\Domain\Leave\Models\LeaveRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequestRequest;
use App\Http\Requests\Leave\UpdateLeaveRequestRequest;
use App\Http\Requests\Leave\UpdateLeaveRequestStatusRequest;
use App\Http\Resources\LeaveRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service)
    {
    }

    public function filterOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->filterOptionsForActor($request->user()),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'date' => $request->query('date'),
            'status' => $request->query('status'),
            'leave_type' => $request->query('leave_type'),
            'employee_id' => $request->query('employee_id'),
            'designation' => $request->query('designation'),
            'mine' => $request->query('mine'),
        ];

        return LeaveRequestResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $leaveRequest = $this->service->createForActor(
            $request->user(),
            LeaveRequestData::fromArray($request->validated())
        );

        return (new LeaveRequestResource($leaveRequest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, LeaveRequest $leave_request): LeaveRequestResource
    {
        return new LeaveRequestResource(
            $this->service->findForActor($request->user(), $leave_request)
        );
    }

    public function update(
        UpdateLeaveRequestRequest $request,
        LeaveRequest $leave_request,
    ): LeaveRequestResource {
        $updated = $this->service->updateForActor(
            $request->user(),
            $leave_request,
            $request->validated()
        );

        return new LeaveRequestResource($updated);
    }

    public function updateStatus(
        UpdateLeaveRequestStatusRequest $request,
        LeaveRequest $leave_request,
    ): LeaveRequestResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $leave_request,
            (string) $validated['status'],
            $validated['cancel_reason'] ?? null,
        );

        return new LeaveRequestResource($updated);
    }

    public function destroy(Request $request, LeaveRequest $leave_request): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $leave_request);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
