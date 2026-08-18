<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Platform\PayrollCycleData;
use App\Application\Services\PayrollCycleService;
use App\Domain\Platform\Models\PayrollCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePayrollCycleRequest;
use App\Http\Requests\Platform\TogglePayrollCycleStatusRequest;
use App\Http\Requests\Platform\UpdatePayrollCycleRequest;
use App\Http\Resources\PayrollCycleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PayrollCycleController extends Controller
{
    public function __construct(private readonly PayrollCycleService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $onlyActive = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOL);

        $cycles = $onlyActive ? $this->service->listActive() : $this->service->list();

        return PayrollCycleResource::collection($cycles);
    }

    public function store(StorePayrollCycleRequest $request): JsonResponse
    {
        $cycle = $this->service->create(PayrollCycleData::fromArray($request->validated()));

        return (new PayrollCycleResource($cycle))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PayrollCycle $payroll_cycle): PayrollCycleResource
    {
        return new PayrollCycleResource($payroll_cycle);
    }

    public function update(
        UpdatePayrollCycleRequest $request,
        PayrollCycle $payroll_cycle,
    ): PayrollCycleResource {
        $updated = $this->service->update($payroll_cycle, $request->validated());

        return new PayrollCycleResource($updated);
    }

    public function toggleStatus(
        TogglePayrollCycleStatusRequest $request,
        PayrollCycle $payroll_cycle,
    ): PayrollCycleResource {
        $updated = $this->service->toggleStatus(
            $payroll_cycle,
            (bool) $request->validated('is_active'),
        );

        return new PayrollCycleResource($updated);
    }

    public function destroy(Request $request, PayrollCycle $payroll_cycle): JsonResponse
    {
        abort_unless(
            in_array($request->user()?->role, ['super_admin', 'tenant', 'admin'], true),
            Response::HTTP_FORBIDDEN
        );

        $this->service->delete($payroll_cycle);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
