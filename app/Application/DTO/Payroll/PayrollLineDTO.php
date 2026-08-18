<?php

declare(strict_types=1);

namespace App\Application\DTO\Payroll;

/**
 * One computed payroll line: an earning, a deduction, a tax, a modifier or a summary row.
 */
final class PayrollLineDTO
{
    public function __construct(
        public string $kind,                  // component | tax_employer | tax_employee | modifier | summary
        public string $code,
        public string $label,
        public string $type,                  // earning | deduction | info
        public float $baseAmount,
        public float $rate,
        public float $amount,
        public bool $isTaxable = false,
        public bool $affectsGross = false,
        public bool $affectsNet = false,
        public int $sequence = 100,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        /** @var array<string, mixed> */
        public array $meta = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'code' => $this->code,
            'label' => $this->label,
            'type' => $this->type,
            'base_amount' => round($this->baseAmount, 2),
            'rate' => round($this->rate, 4),
            'amount' => round($this->amount, 2),
            'is_taxable' => $this->isTaxable,
            'affects_gross' => $this->affectsGross,
            'affects_net' => $this->affectsNet,
            'sequence' => $this->sequence,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'meta' => $this->meta,
        ];
    }
}
