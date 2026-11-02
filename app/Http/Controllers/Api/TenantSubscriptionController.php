<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\TenantSubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\ChangeTenantSubscriptionRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TenantSubscriptionController extends Controller
{
    public function __construct(private readonly TenantSubscriptionService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->service->getForActor($request->user()));
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->dashboardForActor($request->user()),
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->usageForActor($request->user()),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->invoicesForActor($request->user()),
        ]);
    }

    public function showInvoice(Request $request, int $invoice): JsonResponse
    {
        return response()->json([
            'data' => $this->service->invoiceForActor($request->user(), $invoice),
        ]);
    }

    public function downloadInvoice(Request $request, int $invoice): Response
    {
        return $this->service->downloadInvoicePdfForActor($request->user(), $invoice);
    }

    public function billings(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->billingsForActor($request->user()),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        return response()->json($this->service->cancelForActor($request->user()));
    }

    public function changePlan(ChangeTenantSubscriptionRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->service->changePlanForActor(
                    $request->user(),
                    $request->validated(),
                    $request->file('receipt'),
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], HttpResponse::HTTP_FORBIDDEN);
        }
    }
}
