<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Attendance\ShiftData;
use App\Application\Services\ShiftService;
use App\Domain\Attendance\Models\Shift;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreShiftRequest;
use App\Http\Requests\Attendance\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ShiftResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->service->createForActor(
            $request->user(),
            ShiftData::fromArray($request->validated())
        );

        return (new ShiftResource($shift))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Shift $shift): ShiftResource
    {
        return new ShiftResource(
            $this->service->findForActor($request->user(), $shift)
        );
    }

    public function update(UpdateShiftRequest $request, Shift $shift): ShiftResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $shift,
            $request->validated()
        );

        return new ShiftResource($updated);
    }

    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $shift);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Shift $shift): ShiftResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $shift);

        return new ShiftResource($updated);
    }
}
