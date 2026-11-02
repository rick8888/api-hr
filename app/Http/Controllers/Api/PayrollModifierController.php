<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PayrollModifierService;
use App\Application\Services\TenantCompanyScope;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\PayrollModifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollModifierRequest;
use App\Http\Requests\Payroll\UpdatePayrollModifierRequest;
use App\Http\Resources\PayrollModifierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayrollModifierController extends Controller
{
    public function __construct(
        private readonly PayrollModifierService $service,
        private readonly TenantCompanyScope $tenantScope,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 100);
        $companyId = $this->tenantScope->companyIdFor(
            $request->user(),
            $request->integer('company_id') ?: null,
        );

        return PayrollModifierResource::collection(
            $this->service->paginate($perPage, $companyId ? (int) $companyId : null)
        );
    }

    public function store(StorePayrollModifierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['company_id'] = $this->tenantScope->companyIdFor(
            $request->user(),
            isset($data['company_id']) ? (int) $data['company_id'] : null,
        );
        $this->ensureEmployeeScope($request, $data);
        if (($data['scope'] ?? null) === PayrollModifier::SCOPE_GLOBAL) {
            $data['employee_id'] = null;
        }

        $modifier = $this->service->create($data);

        return (new PayrollModifierResource($modifier))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, PayrollModifier $payroll_modifier): PayrollModifierResource
    {
        $this->ensureModifierAccess($request, $payroll_modifier);
        $modifier = $this->service->find($payroll_modifier->id) ?? $payroll_modifier;

        return new PayrollModifierResource($modifier);
    }

    public function update(
        UpdatePayrollModifierRequest $request,
        PayrollModifier $payroll_modifier,
    ): PayrollModifierResource {
        $this->ensureModifierAccess($request, $payroll_modifier);
        $data = $request->validated();
        if ($request->user()->role !== 'super_admin') {
            $data['company_id'] = (int) $request->user()->company_id;
        }
        if (($data['scope'] ?? $payroll_modifier->scope) === PayrollModifier::SCOPE_GLOBAL) {
            $data['employee_id'] = null;
        } else {
            $this->ensureEmployeeScope($request, array_merge([
                'employee_id' => $payroll_modifier->employee_id,
                'scope' => $payroll_modifier->scope,
            ], $data));
        }

        $updated = $this->service->update($payroll_modifier, $data);

        return new PayrollModifierResource($updated);
    }

    public function destroy(Request $request, PayrollModifier $payroll_modifier): JsonResponse
    {
        $this->ensureModifierAccess($request, $payroll_modifier);
        $this->service->delete($payroll_modifier);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureModifierAccess(Request $request, PayrollModifier $modifier): void
    {
        $actor = $request->user();
        if ($actor->role === 'super_admin') {
            return;
        }

        if ($modifier->employee_id !== null) {
            $employee = $modifier->employee;
            if ($employee === null) {
                abort(Response::HTTP_NOT_FOUND);
            }
            $this->tenantScope->ensureOwnsCompany($actor, $employee->company_id);
        }

        if ((int) $modifier->company_id === (int) $actor->company_id) {
            return;
        }

        $employeeCompanyId = $modifier->employee?->company_id;
        if ($modifier->company_id === null && $employeeCompanyId !== null) {
            $this->tenantScope->ensureOwnsCompany($actor, (int) $employeeCompanyId);

            return;
        }

        $this->tenantScope->ensureOwnsCompany($actor, $modifier->company_id);
    }

    /** @param array<string, mixed> $data */
    private function ensureEmployeeScope(Request $request, array $data): void
    {
        if (($data['scope'] ?? null) !== PayrollModifier::SCOPE_EMPLOYEE) {
            return;
        }

        $employee = Employee::query()->find((int) ($data['employee_id'] ?? 0));
        if ($employee === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->tenantScope->ensureOwnsCompany($request->user(), (int) $employee->company_id);
    }
}
