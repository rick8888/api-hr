<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\AdminDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', 'today');
        $validated = $request->validate([
            'schedule_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'data' => $this->service->showForActor(
                $request->user(),
                $period,
                $validated['schedule_date'] ?? null,
            ),
        ]);
    }
}
