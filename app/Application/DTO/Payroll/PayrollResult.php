<?php

declare(strict_types=1);

namespace App\Application\DTO\Payroll;

/**
 * Mutable accumulator that engines populate during a payroll run.
 *
 * Engines append PayrollLineDTOs and update the running totals.
 */
final class PayrollResult
{
    /** @var list<PayrollLineDTO> */
    public array $lines = [];

    public float $base = 0.0;
    public float $gross = 0.0;
    public float $taxable = 0.0;
    public float $net = 0.0;

    public float $totalEarnings = 0.0;
    public float $totalDeductions = 0.0;
    public float $totalEmployerCharges = 0.0;
    public float $totalEmployeeCharges = 0.0;

    /** @var array<string, float> Variables exposed to the FormulaEngine */
    public array $variables = [];

    /** @var list<array<string, mixed>> Step-by-step audit trail of the computation */
    public array $audit = [];

    public function addLine(PayrollLineDTO $line): void
    {
        $this->lines[] = $line;
    }

    /** @param array<string, mixed> $entry */
    public function trace(string $step, array $entry = []): void
    {
        $this->audit[] = ['step' => $step] + $entry;
    }

    public function setVariable(string $name, float $value): void
    {
        $this->variables[$name] = $value;
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'base' => round($this->base, 2),
            'gross' => round($this->gross, 2),
            'taxable' => round($this->taxable, 2),
            'net' => round($this->net, 2),
            'total_earnings' => round($this->totalEarnings, 2),
            'total_deductions' => round($this->totalDeductions, 2),
            'total_employer_charges' => round($this->totalEmployerCharges, 2),
            'total_employee_charges' => round($this->totalEmployeeCharges, 2),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'lines' => array_map(fn (PayrollLineDTO $l) => $l->toArray(), $this->lines),
            'variables' => $this->variables,
            'audit' => $this->audit,
        ];
    }
}
