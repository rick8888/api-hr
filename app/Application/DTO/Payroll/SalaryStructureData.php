<?php

declare(strict_types=1);

namespace App\Application\DTO\Payroll;

final class SalaryStructureLineData
{
    public function __construct(
        public readonly int $salaryComponentId,
        public readonly ?float $amountOverride = null,
        public readonly ?string $formulaOverride = null,
        public readonly ?float $rateOverride = null,
        public readonly int $sequence = 100,
        public readonly bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            salaryComponentId: (int) $data['salary_component_id'],
            amountOverride: isset($data['amount_override']) ? (float) $data['amount_override'] : null,
            formulaOverride: $data['formula_override'] ?? null,
            rateOverride: isset($data['rate_override']) ? (float) $data['rate_override'] : null,
            sequence: (int) ($data['sequence'] ?? 100),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'salary_component_id' => $this->salaryComponentId,
            'amount_override' => $this->amountOverride,
            'formula_override' => $this->formulaOverride,
            'rate_override' => $this->rateOverride,
            'sequence' => $this->sequence,
            'is_active' => $this->isActive,
        ];
    }
}

final class SalaryStructureData
{
    /** @param list<SalaryStructureLineData> $lines */
    public function __construct(
        public readonly int $employeeId,
        public readonly float $baseSalary,
        public readonly string $effectiveFrom,
        public readonly ?string $effectiveTo = null,
        public readonly ?string $currency = null,
        public readonly ?string $name = null,
        public readonly string $status = 'active',
        public readonly array $lines = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = SalaryStructureLineData::fromArray($line);
        }

        return new self(
            employeeId: (int) $data['employee_id'],
            baseSalary: (float) $data['base_salary'],
            effectiveFrom: (string) $data['effective_from'],
            effectiveTo: $data['effective_to'] ?? null,
            currency: $data['currency'] ?? null,
            name: $data['name'] ?? null,
            status: $data['status'] ?? 'active',
            lines: $lines,
        );
    }
}
