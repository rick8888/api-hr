<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SelfServiceLeaveBalanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceLeaveBalanceController extends Controller
{
    public function __construct(private readonly SelfServiceLeaveBalanceService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', (int) now()->format('Y'));
        abort_unless($year >= 2000 && $year <= 2100, 422, 'Année invalide.');

        return response()->json([
            'data' => $this->service->showForActor($request->user(), $year),
        ]);
    }
}
