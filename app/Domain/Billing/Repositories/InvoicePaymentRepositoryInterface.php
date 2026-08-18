<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoicePayment;
use Illuminate\Support\Collection;

interface InvoicePaymentRepositoryInterface
{
    /** @return Collection<int, InvoicePayment> */
    public function list(?int $invoiceId = null): Collection;

    public function sumForInvoice(int $invoiceId): float;

    public function lastPaidAtForInvoice(int $invoiceId): ?string;

    /** @param array<string, mixed> $data */
    public function create(Invoice $invoice, array $data): InvoicePayment;

    public function delete(InvoicePayment $payment): bool;
}
