<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Attendance\AttendanceRecordData;
use App\Application\Services\AttendanceRecordService;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRecordRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AttendanceRecordController extends Controller
{
    public function __construct(private readonly AttendanceRecordService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AttendanceRecordResource::collection($this->service->listForActor(
            $request->user(),
            $request->only(['date', 'from', 'to', 'employee_id', 'status', 'mine']),
        ));
    }

    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $record = $this->service->createForActor(
            $request->user(),
            AttendanceRecordData::fromArray($request->validated()),
        );

        return (new AttendanceRecordResource($record))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateAttendanceRecordRequest $request,
        AttendanceRecord $attendance_record,
    ): AttendanceRecordResource {
        return new AttendanceRecordResource($this->service->updateForActor(
            $request->user(),
            $attendance_record,
            $request->validated(),
        ));
    }

    public function destroy(Request $request, AttendanceRecord $attendance_record): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $attendance_record);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
