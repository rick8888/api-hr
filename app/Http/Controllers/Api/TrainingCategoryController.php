<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\TrainingCategoryData;
use App\Application\Services\TrainingCategoryService;
use App\Domain\Platform\Models\TrainingCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTrainingCategoryRequest;
use App\Http\Requests\Platform\ToggleTrainingCategoryStatusRequest;
use App\Http\Requests\Platform\UpdateTrainingCategoryRequest;
use App\Http\Resources\TrainingCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TrainingCategoryController extends Controller
{
    public function __construct(private readonly TrainingCategoryService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $categories = $onlyActive ? $this->service->listActive() : $this->service->list();

        return TrainingCategoryResource::collection($categories);
    }

    public function store(StoreTrainingCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create(TrainingCategoryData::fromArray($request->validated()));

        return (new TrainingCategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TrainingCategory $training_category): TrainingCategoryResource
    {
        return new TrainingCategoryResource($training_category);
    }

    public function update(
        UpdateTrainingCategoryRequest $request,
        TrainingCategory $training_category,
    ): TrainingCategoryResource {
        $updated = $this->service->update($training_category, $request->validated());

        return new TrainingCategoryResource($updated);
    }

    public function toggleStatus(
        ToggleTrainingCategoryStatusRequest $request,
        TrainingCategory $training_category,
    ): TrainingCategoryResource {
        $updated = $this->service->toggleStatus(
            $training_category,
            (bool) $request->validated('is_active'),
        );

        return new TrainingCategoryResource($updated);
    }

    public function destroy(Request $request, TrainingCategory $training_category): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($training_category);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
