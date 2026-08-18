<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EquipmentRequestService;
use App\Domain\Equipment\Models\EquipmentRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentRequestRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequestStatusRequest;
use App\Http\Resources\EquipmentRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EquipmentRequestController extends Controller
{
    public function __construct(private readonly EquipmentRequestService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return EquipmentRequestResource::collection(
            $this->service->listForActor($request->user(), [
                'mine' => $request->query('mine'),
                'status' => $request->query('status'),
                'equipment_id' => $request->query('equipment_id'),
                'employee_id' => $request->query('employee_id'),
            ])
        );
    }

    public function store(StoreEquipmentRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $row = $this->service->createForActor(
            $request->user(),
            (int) $validated['equipment_id'],
            (int) $validated['quantity'],
            (string) ($validated['priority'] ?? 'medium'),
            $validated['reason'] ?? null,
            $validated['expected_date'] ?? null,
        );

        return (new EquipmentRequestResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, EquipmentRequest $equipment_request): EquipmentRequestResource
    {
        return new EquipmentRequestResource(
            $this->service->findForActor($request->user(), $equipment_request)
        );
    }

    public function updateStatus(
        UpdateEquipmentRequestStatusRequest $request,
        EquipmentRequest $equipment_request,
    ): EquipmentRequestResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $equipment_request,
            (string) $validated['status'],
            $validated['reject_reason'] ?? null,
            $validated['serial_number'] ?? null,
        );

        return new EquipmentRequestResource($updated);
    }

    public function destroy(Request $request, EquipmentRequest $equipment_request): JsonResponse
    {
        $this->service->cancelForActor($request->user(), $equipment_request);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
