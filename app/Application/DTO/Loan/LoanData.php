<?php

declare(strict_types=1);

namespace App\Application\DTO\Loan;

use App\Domain\Loan\Models\Loan;

final class LoanData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $type,
        public readonly float $amount,
        public readonly float $interestRate,
        public readonly int $tenureMonths,
        public readonly float $emi,
        public readonly string $purpose,
        public readonly string $currency = 'USD',
        public readonly ?string $notes = null,
        public readonly ?string $disbursementDate = null,
        public readonly ?string $repaymentStartDate = null,
        public readonly ?array $documents = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = strtolower((string) ($data['type'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);
        $tenure = (int) ($data['tenure_months'] ?? $data['tenureMonths'] ?? $data['tenure'] ?? 0);
        $rate = isset($data['interest_rate']) || isset($data['interestRate'])
            ? (float) ($data['interest_rate'] ?? $data['interestRate'])
            : (float) (Loan::TYPES[$type] ?? 0);
        $emi = isset($data['emi'])
            ? (float) $data['emi']
            : self::calculateEmi($amount, $rate, $tenure);

        return new self(
            employeeId: (int) ($data['employee_id'] ?? $data['employeeId'] ?? 0),
            type: $type,
            amount: $amount,
            interestRate: $rate,
            tenureMonths: $tenure,
            emi: $emi,
            purpose: (string) ($data['purpose'] ?? ''),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            disbursementDate: self::nullableDate($data['disbursement_date'] ?? $data['disbursementDate'] ?? null),
            repaymentStartDate: self::nullableDate($data['repayment_start_date'] ?? $data['repaymentStartDate'] ?? null),
            documents: isset($data['documents']) && is_array($data['documents']) ? $data['documents'] : null,
        );
    }

    public static function calculateEmi(float $principal, float $annualRate, int $months): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }

        $r = $annualRate / 100 / 12;
        if ($r == 0.0) {
            return round($principal / $months, 2);
        }

        $emi = ($principal * $r * pow(1 + $r, $months)) / (pow(1 + $r, $months) - 1);

        return round($emi, 2);
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interest_rate' => $this->interestRate,
            'tenure_months' => $this->tenureMonths,
            'emi' => $this->emi,
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'disbursement_date' => $this->disbursementDate,
            'repayment_start_date' => $this->repaymentStartDate,
            'documents' => $this->documents,
        ];
    }

    private static function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
