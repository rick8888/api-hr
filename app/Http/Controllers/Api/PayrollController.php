<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PayrollService;
use App\Application\Services\PayslipPdfService;
use App\Application\Services\TenantCompanyScope;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use App\Engines\Exceptions\PayrollException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\GeneratePayrollRequest;
use App\Http\Requests\Payroll\UpdatePayrollStatusRequest;
use App\Http\Resources\PayrollResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $service,
        private readonly PayslipPdfService $payslipPdf,
        private readonly TenantCompanyScope $tenantScope,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $this->tenantScope->companyIdFor(
            $request->user(),
            $request->integer('company_id') ?: null,
        );

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

    public function show(Request $request, Payroll $payroll): PayrollResource
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), (int) $payroll->company_id);
        $payroll->load(['lines', 'employee', 'company', 'country']);

        return new PayrollResource($payroll);
    }

    public function downloadPdf(Request $request, Payroll $payroll): Response
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), (int) $payroll->company_id);

        return $this->payslipPdf->download($payroll);
    }

    public function updateStatus(UpdatePayrollStatusRequest $request, Payroll $payroll): PayrollResource|JsonResponse
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), (int) $payroll->company_id);

        try {
            $updated = $this->service->updateStatus(
                $payroll,
                $request->string('status')->toString(),
                $request->user()?->id,
                ['reason' => $request->input('reason')],
            );
        } catch (PayrollException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new PayrollResource($updated);
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        $employeeQuery = Employee::query();
        $companyId = $this->tenantScope->companyIdFor($request->user());
        if ($companyId !== null) {
            $employeeQuery->where('company_id', $companyId);
        }

        $employee = $employeeQuery->findOrFail($request->integer('employee_id'));

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
        $employeeQuery = Employee::query();
        $companyId = $this->tenantScope->companyIdFor($request->user());
        if ($companyId !== null) {
            $employeeQuery->where('company_id', $companyId);
        }

        $employee = $employeeQuery->findOrFail($request->integer('employee_id'));

        try {
            $result = $this->service->preview($employee, $request->string('period')->toString());
        } catch (PayrollException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }

    public function destroy(Request $request, Payroll $payroll): JsonResponse
    {
        $this->tenantScope->ensureOwnsCompany($request->user(), (int) $payroll->company_id);
        $this->service->delete($payroll);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
