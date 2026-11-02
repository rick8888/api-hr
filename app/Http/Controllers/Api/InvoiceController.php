<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\DTO\Billing\InvoiceData;
use App\Application\Services\InvoiceService;
use App\Domain\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Requests\Billing\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $this->service->paginate(
                (int) $request->query('per_page', 15),
                $request->integer('company_id') ?: null,
                $request->query('status') ?: null,
            )
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->service->create(InvoiceData::fromArray($request->validated()));

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice = $this->service->find($invoice->id) ?? $invoice;

        return new InvoiceResource($invoice);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $updated = $this->service->updateFromValidated($invoice, $request->validated());

        return new InvoiceResource($updated);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->service->delete($invoice);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function settle(Invoice $invoice): InvoiceResource
    {
        $settled = $this->service->settle($invoice);

        return new InvoiceResource($settled);
    }

    public function paymentProof(Invoice $invoice): HttpResponse
    {
        abort_unless($invoice->payment_proof_path, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($invoice->payment_proof_path), Response::HTTP_NOT_FOUND);

        return $disk->response(
            $invoice->payment_proof_path,
            basename($invoice->payment_proof_path),
            ['Content-Disposition' => 'inline'],
        );
    }
}
