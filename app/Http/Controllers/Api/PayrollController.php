<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PayrollService;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use App\Engines\Exceptions\PayrollException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\GeneratePayrollRequest;
use App\Http\Resources\PayrollResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->integer('company_id')
            ?: $request->user()?->company_id;

        $filters = array_filter([
            'period' => $request->query('period'),
            'period_from' => $request->query('period_from'),
            'period_to' => $request->query('period_to'),
            'status' => $request->query('status'),
            'employee_id' => $request->query('employee_id'),
            'department' => $request->query('department'),
            'search' => $request->query('search') ?? $request->query('q'),
        ], static fn ($v) => $v !== null && $v !== '');

        return PayrollResource::collection(
            $this->service->paginate(
                (int) $request->query('per_page', 15),
                $companyId ? (int) $companyId : null,
                $filters,
            )
        );
    }

    public function show(Payroll $payroll): PayrollResource
    {
        $payroll->load(['lines', 'employee', 'company', 'country']);
        return new PayrollResource($payroll);
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        try {
            $payroll = $this->service->generate(
                $employee,
                $request->string('period')->toString(),
                $request->user()?->id,
            );
        } catch (PayrollException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new PayrollResource($payroll))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function preview(GeneratePayrollRequest $request): JsonResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        try {
            $result = $this->service->preview($employee, $request->string('period')->toString());
        } catch (PayrollException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }

    public function destroy(Payroll $payroll): JsonResponse
    {
        $this->service->delete($payroll);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
