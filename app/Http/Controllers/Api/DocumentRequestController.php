<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\DocumentRequest\DocumentRequestData;
use App\Application\Services\DocumentRequestService;
use App\Domain\DocumentRequest\Models\DocumentRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest\StoreDocumentRequestRequest;
use App\Http\Requests\DocumentRequest\UpdateDocumentRequestStatusRequest;
use App\Http\Resources\DocumentRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DocumentRequestController extends Controller
{
    public function __construct(private readonly DocumentRequestService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'employee_id' => $request->query('employee_id'),
            'employee_code' => $request->query('employee_code'),
            'mine' => $request->query('mine'),
        ];

        if (! empty($filters['type'])) {
            $filters['type'] = DocumentRequestData::normalizeType((string) $filters['type']);
        }

        if (! empty($filters['status'])) {
            $filters['status'] = strtolower(str_replace(
                [' ', '-'],
                '_',
                (string) $filters['status'],
            ));
        }

        return DocumentRequestResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreDocumentRequestRequest $request): JsonResponse
    {
        $row = $this->service->createForActor(
            $request->user(),
            DocumentRequestData::fromArray($request->validated()),
        );

        return (new DocumentRequestResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, DocumentRequest $document_request): DocumentRequestResource
    {
        return new DocumentRequestResource(
            $this->service->findForActor($request->user(), $document_request)
        );
    }

    public function updateStatus(
        UpdateDocumentRequestStatusRequest $request,
        DocumentRequest $document_request,
    ): DocumentRequestResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $document_request,
            (string) $validated['status'],
            $validated['reject_reason'] ?? null,
            $request->file('file'),
        );

        return new DocumentRequestResource($updated);
    }

    public function destroy(Request $request, DocumentRequest $document_request): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $document_request);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
