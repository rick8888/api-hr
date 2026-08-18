<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Organization\DepartmentData;
use App\Application\Services\DepartmentService;
use App\Domain\Organization\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DepartmentResource::collection(
            $this->service->listForActor($request->user())
        );
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->service->createForActor(
            $request->user(),
            DepartmentData::fromArray($request->validated())
        );

        return (new DepartmentResource($department))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        return new DepartmentResource(
            $this->service->findForActor($request->user(), $department)
        );
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $updated = $this->service->updateForActor(
            $request->user(),
            $department,
            $request->validated()
        );

        return new DepartmentResource($updated);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $this->service->deleteForActor($request->user(), $department);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Request $request, Department $department): DepartmentResource
    {
        $updated = $this->service->toggleStatusForActor($request->user(), $department);

        return new DepartmentResource($updated);
    }
}
