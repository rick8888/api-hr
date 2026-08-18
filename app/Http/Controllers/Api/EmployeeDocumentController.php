<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\EmployeeDocument\EmployeeDocumentData;
use App\Application\Services\EmployeeDocumentService;
use App\Domain\EmployeeDocument\Models\EmployeeDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeDocument\StoreEmployeeDocumentRequest;
use App\Http\Requests\EmployeeDocument\UpdateEmployeeDocumentStatusRequest;
use App\Http\Resources\EmployeeDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EmployeeDocumentController extends Controller
{
    public function __construct(private readonly EmployeeDocumentService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'category' => $request->query('category'),
            'status' => $request->query('status'),
            'expiry' => $request->query('expiry'),
            'employee_id' => $request->query('employee_id'),
            'employee_code' => $request->query('employee_code'),
            'mine' => $request->query('mine'),
        ];

        return EmployeeDocumentResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreEmployeeDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['file']);

        $row = $this->service->createForActor(
            $request->user(),
            EmployeeDocumentData::fromArray($validated),
            $request->file('file'),
        );

        return (new EmployeeDocumentResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, EmployeeDocument $employee_document): EmployeeDocumentResource
    {
        return new EmployeeDocumentResource(
            $this->service->findForActor($request->user(), $employee_document)
        );
    }

    public function updateStatus(
        UpdateEmployeeDocumentStatusRequest $request,
        EmployeeDocument $employee_document,
    ): EmployeeDocumentResource {
        $validated = $request->validated();

        $updated = $this->service->updateStatusForActor(
            $request->user(),
            $employee_document,
            (string) $validated['status'],
            $validated['reject_reason'] ?? null,
        );

        return new EmployeeDocumentResource($updated);
    }

    public function destroy(Request $request, EmployeeDocument $employee_document): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $employee_document);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
