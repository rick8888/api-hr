<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\RoleData;
use App\Application\Services\RoleService;
use App\Domain\Platform\Models\Role;
use App\Domain\Platform\RolePermissionCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreRoleRequest;
use App\Http\Requests\Platform\ToggleRoleStatusRequest;
use App\Http\Requests\Platform\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service)
    {
    }

    public function permissionCatalog(): JsonResponse
    {
        return response()->json([
            'data' => RolePermissionCatalog::all(),
        ]);
    }

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->service->list());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->service->create(RoleData::fromArray($request->validated()));

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $updated = $this->service->update($role, $request->validated());

        return new RoleResource($updated);
    }

    public function toggleStatus(
        ToggleRoleStatusRequest $request,
        Role $role,
    ): RoleResource {
        $updated = $this->service->toggleStatus($role, (bool) $request->validated('is_active'));

        return new RoleResource($updated);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->service->delete($role);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
