<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\DocumentCategoryData;
use App\Application\Services\DocumentCategoryService;
use App\Domain\Platform\Models\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreDocumentCategoryRequest;
use App\Http\Requests\Platform\ToggleDocumentCategoryStatusRequest;
use App\Http\Requests\Platform\UpdateDocumentCategoryRequest;
use App\Http\Resources\DocumentCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DocumentCategoryController extends Controller
{
    public function __construct(private readonly DocumentCategoryService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $categories = $onlyActive ? $this->service->listActive() : $this->service->list();

        return DocumentCategoryResource::collection($categories);
    }

    public function store(StoreDocumentCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create(DocumentCategoryData::fromArray($request->validated()));

        return (new DocumentCategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DocumentCategory $document_category): DocumentCategoryResource
    {
        return new DocumentCategoryResource($document_category);
    }

    public function update(
        UpdateDocumentCategoryRequest $request,
        DocumentCategory $document_category,
    ): DocumentCategoryResource {
        $updated = $this->service->update($document_category, $request->validated());

        return new DocumentCategoryResource($updated);
    }

    public function toggleStatus(
        ToggleDocumentCategoryStatusRequest $request,
        DocumentCategory $document_category,
    ): DocumentCategoryResource {
        $updated = $this->service->toggleStatus(
            $document_category,
            (bool) $request->validated('is_active'),
        );

        return new DocumentCategoryResource($updated);
    }

    public function destroy(Request $request, DocumentCategory $document_category): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($document_category);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
