<?php

declare(strict_types=1);

namespace App\Application\DTO\Expense;

final class ExpenseData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $type,
        public readonly string $expenseDate,
        public readonly float $amount,
        public readonly string $currency = 'USD',
        public readonly ?string $description = null,
        public readonly ?string $receiptPath = null,
        public readonly string $status = 'pending',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = strtolower(str_replace([' ', '-'], '_', (string) ($data['type'] ?? '')));

        return new self(
            employeeId: (int) ($data['employee_id'] ?? $data['employeeId'] ?? 0),
            type: $type,
            expenseDate: (string) ($data['expense_date'] ?? $data['expenseDate'] ?? ''),
            amount: (float) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            description: isset($data['description'])
                ? (string) $data['description']
                : (isset($data['remarks']) ? (string) $data['remarks'] : null),
            receiptPath: isset($data['receipt_path']) || isset($data['receiptPath'])
                ? (string) ($data['receipt_path'] ?? $data['receiptPath'])
                : null,
            status: (string) ($data['status'] ?? 'pending'),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'type' => $this->type,
            'expense_date' => $this->expenseDate,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'receipt_path' => $this->receiptPath,
            'status' => $this->status,
        ];
    }
}
