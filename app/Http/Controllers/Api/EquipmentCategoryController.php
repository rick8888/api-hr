<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\EquipmentCategoryData;
use App\Application\Services\EquipmentCategoryService;
use App\Domain\Platform\Models\EquipmentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreEquipmentCategoryRequest;
use App\Http\Requests\Platform\ToggleEquipmentCategoryStatusRequest;
use App\Http\Requests\Platform\UpdateEquipmentCategoryRequest;
use App\Http\Resources\EquipmentCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EquipmentCategoryController extends Controller
{
    public function __construct(private readonly EquipmentCategoryService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $categories = $onlyActive ? $this->service->listActive() : $this->service->list();

        return EquipmentCategoryResource::collection($categories);
    }

    public function store(StoreEquipmentCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create(EquipmentCategoryData::fromArray($request->validated()));

        return (new EquipmentCategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(EquipmentCategory $equipment_category): EquipmentCategoryResource
    {
        return new EquipmentCategoryResource($equipment_category);
    }

    public function update(
        UpdateEquipmentCategoryRequest $request,
        EquipmentCategory $equipment_category,
    ): EquipmentCategoryResource {
        $updated = $this->service->update($equipment_category, $request->validated());

        return new EquipmentCategoryResource($updated);
    }

    public function toggleStatus(
        ToggleEquipmentCategoryStatusRequest $request,
        EquipmentCategory $equipment_category,
    ): EquipmentCategoryResource {
        $updated = $this->service->toggleStatus(
            $equipment_category,
            (bool) $request->validated('is_active'),
        );

        return new EquipmentCategoryResource($updated);
    }

    public function destroy(Request $request, EquipmentCategory $equipment_category): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($equipment_category);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
