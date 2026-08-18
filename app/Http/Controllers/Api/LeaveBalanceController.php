<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\LeaveBalanceService;
use App\Domain\Leave\Models\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\SetInitialLeaveBalanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function __construct(private readonly LeaveBalanceService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $year = $this->year($request);
        $mine = filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'data' => $this->service->listForActor($request->user(), $year, $mine),
        ]);
    }

    public function show(Request $request, string $employeeCode): JsonResponse
    {
        $year = $this->year($request);

        return response()->json([
            'data' => $this->service->showForActor($request->user(), $employeeCode, $year),
        ]);
    }

    public function setInitial(
        SetInitialLeaveBalanceRequest $request,
        string $employeeCode,
    ): JsonResponse {
        $validated = $request->validated();
        $leaveType = LeaveType::query()->findOrFail((int) $validated['leave_type_id']);

        return response()->json([
            'data' => $this->service->setInitialForActor(
                $request->user(),
                $employeeCode,
                $leaveType,
                (int) $validated['year'],
                (float) $validated['entitled'],
                $validated['reason'] ?? null,
            ),
        ]);
    }

    private function year(Request $request): int
    {
        $year = (int) $request->integer('year', (int) now()->format('Y'));

        abort_unless($year >= 2000 && $year <= 2100, 422, 'Année invalide.');

        return $year;
    }
}
