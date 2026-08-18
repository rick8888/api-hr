<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PayrollModifierService;
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
    public function __construct(private readonly PayrollModifierService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 100);
        $companyId = $request->integer('company_id')
            ?: $request->user()?->company_id;

        return PayrollModifierResource::collection(
            $this->service->paginate($perPage, $companyId ? (int) $companyId : null)
        );
    }

    public function store(StorePayrollModifierRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['company_id']) && $request->user()?->company_id) {
            $data['company_id'] = $request->user()->company_id;
        }
        if (($data['scope'] ?? null) === PayrollModifier::SCOPE_GLOBAL) {
            $data['employee_id'] = null;
        }

        $modifier = $this->service->create($data);

        return (new PayrollModifierResource($modifier))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PayrollModifier $payroll_modifier): PayrollModifierResource
    {
        $modifier = $this->service->find($payroll_modifier->id) ?? $payroll_modifier;

        return new PayrollModifierResource($modifier);
    }

    public function update(
        UpdatePayrollModifierRequest $request,
        PayrollModifier $payroll_modifier,
    ): PayrollModifierResource {
        $data = $request->validated();
        if (($data['scope'] ?? $payroll_modifier->scope) === PayrollModifier::SCOPE_GLOBAL) {
            $data['employee_id'] = null;
        }

        $updated = $this->service->update($payroll_modifier, $data);

        return new PayrollModifierResource($updated);
    }

    public function destroy(PayrollModifier $payroll_modifier): JsonResponse
    {
        $this->service->delete($payroll_modifier);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
