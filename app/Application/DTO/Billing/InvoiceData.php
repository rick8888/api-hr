<?php

declare(strict_types=1);

namespace App\Application\DTO\Billing;

final class InvoiceData
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $description,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $issueDate,
        public readonly string $dueDate,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $planCode = null,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
        public readonly ?string $paidAt = null,
        public readonly ?string $notes = null,
        public readonly ?string $paymentMethod = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'invoice_number' => $this->invoiceNumber,
            'plan_code' => $this->planCode,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'paid_at' => $this->paidAt,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'notes' => $this->notes,
            'payment_method' => $this->paymentMethod,
        ], fn ($v) => $v !== null);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (int) $data['company_id'],
            description: (string) $data['description'],
            amount: (float) $data['amount'],
            currency: (string) ($data['currency'] ?? 'EUR'),
            status: (string) ($data['status'] ?? 'draft'),
            issueDate: (string) $data['issue_date'],
            dueDate: (string) $data['due_date'],
            invoiceNumber: $data['invoice_number'] ?? null,
            planCode: $data['plan_code'] ?? null,
            periodStart: $data['period_start'] ?? null,
            periodEnd: $data['period_end'] ?? null,
            paidAt: $data['paid_at'] ?? null,
            notes: $data['notes'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
        );
    }
}
