<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Payroll\SalaryStructureData;
use App\Application\Services\EmployeeSalaryStructureService;
use App\Domain\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\AssignStructureRequest;
use App\Http\Resources\EmployeeSalaryStructureResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EmployeeStructureController extends Controller
{
    public function __construct(private readonly EmployeeSalaryStructureService $service)
    {
    }

    public function index(Employee $employee): AnonymousResourceCollection
    {
        return EmployeeSalaryStructureResource::collection(
            $this->service->listForEmployee($employee->id)
        );
    }

    public function store(AssignStructureRequest $request, Employee $employee): JsonResponse
    {
        $payload = $request->validated();
        $payload['employee_id'] = $employee->id;

        $structure = $this->service->assignStructure(SalaryStructureData::fromArray($payload));

        return (new EmployeeSalaryStructureResource($structure))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
