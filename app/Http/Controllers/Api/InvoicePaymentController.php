<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\InvoiceService;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoicePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreInvoicePaymentRequest;
use App\Http\Resources\InvoicePaymentResource;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class InvoicePaymentController extends Controller
{
    public function __construct(private readonly InvoiceService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoicePaymentResource::collection(
            $this->service->listPayments($request->integer('invoice_id') ?: null)
        );
    }

    public function store(StoreInvoicePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $result = $this->service->recordPayment($invoice, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'payment' => (new InvoicePaymentResource($result['payment']))->resolve(),
                'invoice' => (new InvoiceResource($result['invoice']))->resolve(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroy(InvoicePayment $invoice_payment): JsonResponse
    {
        $this->service->deletePayment($invoice_payment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
