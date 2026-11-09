<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EmployeeActivityService;
use App\Application\Services\EmployeeImportExportService;
use App\Application\Services\EmployeeService;
use App\Domain\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\BulkUpdateEmployeeStatusRequest;
use App\Http\Requests\Employee\ImportEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\SuspendEmployeeRequest;
use App\Http\Requests\Employee\TerminateEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeAvatarRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $service,
        private readonly EmployeeActivityService $activities,
        private readonly EmployeeImportExportService $importExport,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search', $request->query('q'));
        $department = $request->query('department', $request->query('department_id'));
        $structureStatus = $request->query('structure_status');

        $filters = array_filter([
            'search' => is_string($search) ? $search : null,
            'department' => is_string($department) ? $department : null,
            'structure_status' => is_string($structureStatus) ? $structureStatus : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 'all');

        return EmployeeResource::collection(
            $this->service->listForActor($request->user(), $filters)
        );
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->createForActor(
            $request->user(),
            $request->validated()
        );

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Employee $employee): EmployeeResource
    {
        return new EmployeeResource(
            $this->service->findForActor($request->user(), $employee)
        );
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $employee,
            $request->validated()
        );

        return new EmployeeResource($updated);
    }

    public function updateAvatar(
        UpdateEmployeeAvatarRequest $request,
        Employee $employee,
    ): EmployeeResource {
        $updated = $this->service->updateAvatarForActor(
            $request->user(),
            $employee,
            $request->file('avatar'),
        );

        return new EmployeeResource($updated);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $employee);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Employee $employee): EmployeeResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $employee);

        return new EmployeeResource($updated);
    }

    public function bulkStatus(BulkUpdateEmployeeStatusRequest $request): JsonResponse
    {
        $result = $this->service->bulkSetStatusForActor(
            $request->user(),
            $request->validated('ids'),
            (string) $request->validated('status'),
        );

        return response()->json([
            'data' => [
                'updated' => array_map(
                    static fn (Employee $employee) => (new EmployeeResource($employee))->resolve(),
                    $result['updated'],
                ),
                'skipped' => $result['skipped'],
            ],
        ]);
    }

    public function suspend(SuspendEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $updated = $this->service->suspendForActor(
            $request->user(),
            $employee,
            $request->validated()
        );

        return new EmployeeResource($updated);
    }

    public function terminate(TerminateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $updated = $this->service->terminateForActor(
            $request->user(),
            $employee,
            $request->validated()
        );

        return new EmployeeResource($updated);
    }

    public function retire(Request $request, Employee $employee): EmployeeResource
    {
        $updated = $this->service->retireForActor($request->user(), $employee);

        return new EmployeeResource($updated);
    }

    public function activities(Request $request, Employee $employee): JsonResponse
    {
        $this->service->findForActor($request->user(), $employee);

        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        return response()->json([
            'data' => $this->activities->forEmployee($employee, $limit),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $ids = $request->query('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        return $this->importExport->exportCsvForActor($request->user(), $ids);
    }

    public function import(ImportEmployeeRequest $request): JsonResponse
    {
        $result = $this->importExport->importCsvForActor(
            $request->user(),
            $request->file('file')
        );

        return response()->json(['data' => $result]);
    }
}
