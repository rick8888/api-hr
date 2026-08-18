<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Equipment\EquipmentData;
use App\Application\Services\EquipmentService;
use App\Domain\Equipment\Models\Equipment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EquipmentController extends Controller
{
    public function __construct(private readonly EquipmentService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'category' => $request->query('category'),
            'active' => $request->query('active'),
            'available_only' => $request->query('available_only'),
        ];

        if (! empty($filters['category'])) {
            $filters['category'] = EquipmentData::normalizeCategory((string) $filters['category']);
        }

        return EquipmentResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $row = $this->service->createForActor(
            $request->user(),
            EquipmentData::fromArray($request->validated()),
        );

        return (new EquipmentResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Equipment $equipment): EquipmentResource
    {
        return new EquipmentResource(
            $this->service->findForActor($request->user(), $equipment)
        );
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $equipment,
            $request->validated(),
        );

        return new EquipmentResource($updated);
    }

    public function destroy(Request $request, Equipment $equipment): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $equipment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
