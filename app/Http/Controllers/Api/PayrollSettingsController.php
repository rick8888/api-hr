<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PayrollSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\UpdatePayrollSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollSettingsController extends Controller
{
    public function __construct(private readonly PayrollSettingsService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getForActor($request->user()),
        ]);
    }

    public function update(UpdatePayrollSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->updateForActor($request->user(), $request->validated()),
            'message' => 'Paramètres de paie enregistrés.',
        ]);
    }
}
