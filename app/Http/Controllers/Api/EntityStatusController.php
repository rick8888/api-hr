<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\EntityStatusData;
use App\Application\Services\EntityStatusService;
use App\Domain\Platform\Models\EntityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreEntityStatusRequest;
use App\Http\Requests\Platform\ToggleEntityStatusRequest;
use App\Http\Requests\Platform\UpdateEntityStatusRequest;
use App\Http\Resources\EntityStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EntityStatusController extends Controller
{
    public function __construct(private readonly EntityStatusService $service)
    {
    }

    public function catalogs(): JsonResponse
    {
        abort_unless($this->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        return response()->json([
            'data' => $this->service->catalogs(),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($this->isSuperAdmin() || $request->user() !== null, Response::HTTP_FORBIDDEN);

        $entityType = $request->query('entity_type');
        $entityType = is_string($entityType) && $entityType !== '' ? $entityType : null;
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $rows = $onlyActive
            ? $this->service->listActive($entityType)
            : $this->service->list($entityType);

        return EntityStatusResource::collection($rows);
    }

    public function store(StoreEntityStatusRequest $request): JsonResponse
    {
        $status = $this->service->create(EntityStatusData::fromArray($request->validated()));

        return (new EntityStatusResource($status))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(EntityStatus $entity_status): EntityStatusResource
    {
        abort_unless($this->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        return new EntityStatusResource($entity_status);
    }

    public function update(
        UpdateEntityStatusRequest $request,
        EntityStatus $entity_status,
    ): EntityStatusResource {
        $updated = $this->service->update($entity_status, $request->validated());

        return new EntityStatusResource($updated);
    }

    public function toggleStatus(
        ToggleEntityStatusRequest $request,
        EntityStatus $entity_status,
    ): EntityStatusResource {
        $updated = $this->service->toggleStatus(
            $entity_status,
            (bool) $request->validated('is_active'),
        );

        return new EntityStatusResource($updated);
    }

    public function destroy(Request $request, EntityStatus $entity_status): JsonResponse
    {
        abort_unless($this->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        $this->service->delete($entity_status);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function isSuperAdmin(): bool
    {
        return request()->user()?->role === 'super_admin';
    }
}
