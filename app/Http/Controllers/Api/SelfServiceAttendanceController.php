<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SelfServiceAttendanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceAttendanceController extends Controller
{
    public function __construct(private readonly SelfServiceAttendanceService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->showForActor($request->user()),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->checkInForActor($request->user()),
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->checkOutForActor($request->user()),
        ]);
    }

    public function startBreak(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->startBreakForActor($request->user()),
        ]);
    }

    public function endBreak(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->endBreakForActor($request->user()),
        ]);
    }
}
