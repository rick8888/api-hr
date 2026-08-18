<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SelfServicePayslipsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SelfServicePayslipsController extends Controller
{
    public function __construct(private readonly SelfServicePayslipsService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listForActor($request->user()),
        ]);
    }

    public function show(Request $request, int $payroll): JsonResponse
    {
        return response()->json([
            'data' => $this->service->showForActor($request->user(), $payroll),
        ]);
    }

    public function downloadPdf(Request $request, int $payroll): Response
    {
        return $this->service->downloadPdfForActor($request->user(), $payroll);
    }
}
