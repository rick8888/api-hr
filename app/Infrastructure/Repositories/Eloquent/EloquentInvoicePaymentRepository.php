<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoicePayment;
use App\Domain\Billing\Repositories\InvoicePaymentRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentInvoicePaymentRepository implements InvoicePaymentRepositoryInterface
{
    public function list(?int $invoiceId = null): Collection
    {
        return InvoicePayment::query()
            ->with(['invoice.company'])
            ->when($invoiceId, fn ($q) => $q->where('invoice_id', $invoiceId))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
    }

    public function sumForInvoice(int $invoiceId): float
    {
        return (float) InvoicePayment::query()
            ->where('invoice_id', $invoiceId)
            ->sum('amount');
    }

    public function lastPaidAtForInvoice(int $invoiceId): ?string
    {
        $date = InvoicePayment::query()
            ->where('invoice_id', $invoiceId)
            ->orderByDesc('paid_at')
            ->value('paid_at');

        return $date ? (string) $date : null;
    }

    public function create(Invoice $invoice, array $data): InvoicePayment
    {
        $payment = $invoice->payments()->create($data);

        return $payment->load(['invoice.company']);
    }

    public function delete(InvoicePayment $payment): bool
    {
        return (bool) $payment->delete();
    }
}
