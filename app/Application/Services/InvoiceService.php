<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Billing\InvoiceData;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoicePayment;
use App\Domain\Billing\Repositories\InvoicePaymentRepositoryInterface;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoicePaymentRepositoryInterface $paymentRepository,
        private readonly AuditLogger $audit,
    ) {
    }

    public function paginate(int $perPage = 15, ?int $companyId = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->invoiceRepository->paginate($perPage, $companyId, $status);
    }

    /** @return Collection<int, InvoicePayment> */
    public function listPayments(?int $invoiceId = null): Collection
    {
        return $this->paymentRepository->list($invoiceId);
    }

    public function find(int $id): ?Invoice
    {
        return $this->invoiceRepository->find($id);
    }

    public function create(InvoiceData $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $payload = $data->toModelArray();
            $payload['invoice_number'] = $payload['invoice_number']
                ?? $this->generateInvoiceNumber($data->issueDate);
            $payload['amount_paid'] = 0;

            if ($payload['status'] === Invoice::STATUS_PENDING && $this->isPastDue($data->dueDate)) {
                $payload['status'] = Invoice::STATUS_OVERDUE;
            }

            $invoice = $this->invoiceRepository->create($payload);
            $this->audit->log('invoice.created', $invoice, ['invoice_number' => $invoice->invoice_number]);

            return $this->invoiceRepository->find($invoice->id) ?? $invoice;
        });
    }

    public function update(Invoice $invoice, InvoiceData $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $previousStatus = $invoice->status;
            $payload = $data->toModelArray();
            unset($payload['invoice_number']);

            if (in_array($payload['status'], [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
                $payload['paid_at'] = null;
            }

            $updated = $this->invoiceRepository->update($invoice, $payload);

            if (! in_array($updated->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
                $updated = $this->syncPaymentState($updated);
            }

            $this->audit->log('invoice.updated', $updated, ['invoice_number' => $updated->invoice_number]);

            $fresh = $this->invoiceRepository->find($updated->id) ?? $updated;
            $this->activatePlanIfNewlyPaid($previousStatus, $fresh);

            return $fresh;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateFromValidated(Invoice $invoice, array $validated): Invoice
    {
        $invoice = $this->invoiceRepository->find($invoice->id) ?? $invoice;
        $payload = array_merge($this->modelToArray($invoice), $validated);

        return $this->update($invoice, InvoiceData::fromArray($payload));
    }

    public function delete(Invoice $invoice): bool
    {
        $proofPath = $invoice->payment_proof_path;
        $deleted = DB::transaction(function () use ($invoice) {
            $this->audit->log('invoice.deleted', $invoice, ['invoice_number' => $invoice->invoice_number]);

            return $this->invoiceRepository->delete($invoice);
        });

        if ($deleted && $proofPath !== null) {
            Storage::disk('local')->delete($proofPath);
        }

        return $deleted;
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(Invoice $invoice, array $data): array
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = $this->invoiceRepository->find($invoice->id) ?? $invoice;
            $previousStatus = $invoice->status;

            if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID], true)) {
                throw new InvalidArgumentException('Cette facture n\'accepte pas de paiement.');
            }

            $balance = max(0, (float) $invoice->amount - $this->paymentRepository->sumForInvoice($invoice->id));
            $amount = (float) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new InvalidArgumentException('Le montant doit être supérieur à 0.');
            }

            if ($amount > $balance + 0.001) {
                throw new InvalidArgumentException("Le montant dépasse le solde restant ({$balance}).");
            }

            $payment = $this->paymentRepository->create($invoice, [
                'amount' => $amount,
                'currency' => $invoice->currency,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'method' => $data['method'] ?? InvoicePayment::METHOD_BANK_TRANSFER,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $updatedInvoice = $this->syncPaymentState($invoice);
            $this->audit->log('invoice.payment_recorded', $updatedInvoice, [
                'payment_id' => $payment->id,
                'amount' => $amount,
            ]);

            $fresh = $this->invoiceRepository->find($updatedInvoice->id) ?? $updatedInvoice;
            $this->activatePlanIfNewlyPaid($previousStatus, $fresh);

            return [
                'payment' => $payment,
                'invoice' => $fresh,
            ];
        });
    }

    public function settle(Invoice $invoice): Invoice
    {
        $invoice = $this->invoiceRepository->find($invoice->id) ?? $invoice;
        $balance = max(0, (float) $invoice->amount - $this->paymentRepository->sumForInvoice($invoice->id));

        if ($balance <= 0) {
            // Déjà payée : resynchronise le plan si une facture d'abonnement n'avait pas activé la sub.
            if ($invoice->status === Invoice::STATUS_PAID) {
                $this->activatePlanIfNewlyPaid(Invoice::STATUS_PENDING, $invoice);
            }

            return $invoice;
        }

        $result = $this->recordPayment($invoice, [
            'amount' => $balance,
            'method' => in_array($invoice->payment_method, [
                InvoicePayment::METHOD_BANK_TRANSFER,
                InvoicePayment::METHOD_CASH,
                InvoicePayment::METHOD_MOBILE_MONEY,
            ], true)
                ? $invoice->payment_method
                : InvoicePayment::METHOD_BANK_TRANSFER,
            'reference' => 'SOLDE',
            'notes' => 'Paiement du solde restant',
            'paid_at' => now()->toDateString(),
        ]);

        return $result['invoice'];
    }

    public function deletePayment(InvoicePayment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $deleted = $this->paymentRepository->delete($payment);

            if ($invoice) {
                $this->syncPaymentState($invoice);
            }

            return $deleted;
        });
    }

    public function syncPaymentState(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
            return $invoice;
        }

        $amountPaid = $this->paymentRepository->sumForInvoice($invoice->id);
        $balance = max(0, (float) $invoice->amount - $amountPaid);

        $status = $invoice->status;
        $paidAt = null;

        if ($balance <= 0 && $amountPaid > 0) {
            $status = Invoice::STATUS_PAID;
            $paidAt = $this->paymentRepository->lastPaidAtForInvoice($invoice->id);
        } elseif ($amountPaid > 0) {
            $status = $this->isPastDue((string) $invoice->due_date)
                ? Invoice::STATUS_OVERDUE
                : Invoice::STATUS_PARTIAL;
        } else {
            $status = $this->isPastDue((string) $invoice->due_date)
                ? Invoice::STATUS_OVERDUE
                : Invoice::STATUS_PENDING;
        }

        return $this->invoiceRepository->update($invoice, [
            'amount_paid' => $amountPaid,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * Quand une facture d'abonnement devient paid, active le plan sur la company.
     * Résolution lazy pour éviter une dépendance circulaire constructor Invoice ↔ TenantSubscription.
     */
    private function activatePlanIfNewlyPaid(string $previousStatus, Invoice $invoice): void
    {
        if ($previousStatus === Invoice::STATUS_PAID || $invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        if (trim((string) ($invoice->plan_code ?? '')) === '') {
            return;
        }

        app(TenantSubscriptionService::class)->activateFromPaidInvoice($invoice);
    }

    private function generateInvoiceNumber(string $issueDate): string
    {
        $year = (int) substr($issueDate, 0, 4);
        $seq = $this->invoiceRepository->nextSequenceForYear($year);

        return sprintf('INV-%d-%05d', $year, $seq);
    }

    private function isPastDue(string $dueDate): bool
    {
        return $dueDate < now()->toDateString();
    }

    /** @return array<string, mixed> */
    private function modelToArray(Invoice $invoice): array
    {
        return [
            'company_id' => $invoice->company_id,
            'description' => $invoice->description,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'plan_code' => $invoice->plan_code,
            'period_start' => optional($invoice->period_start)->toDateString(),
            'period_end' => optional($invoice->period_end)->toDateString(),
            'paid_at' => optional($invoice->paid_at)->toDateString(),
            'notes' => $invoice->notes,
        ];
    }
}
