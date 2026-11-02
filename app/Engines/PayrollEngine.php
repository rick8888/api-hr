<?php

declare(strict_types=1);

namespace App\Engines;

use App\Application\DTO\Payroll\PayrollContext;
use App\Application\DTO\Payroll\PayrollLineDTO;
use App\Application\DTO\Payroll\PayrollResult;
use App\Application\Services\PayrollAttendanceCalculator;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Payroll\Models\EmployeeSalaryStructureLine;
use App\Domain\Payroll\Models\PayrollLine;
use App\Domain\Payroll\Models\SalaryComponent;
use App\Domain\Tax\Models\LegalTax;
use App\Engines\Exceptions\PayrollException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PayrollEngine
 *
 * Orchestrates a full payroll computation pipeline for one (employee, period).
 * It does NOT persist anything: it returns a PayrollResult that the application
 * layer is free to translate into Payroll/PayrollLine records.
 *
 * Pipeline:
 *   1. Build context (employee + active structure + base salary).
 *   2. Apply BASE-stage modifiers (advances, prorating, ...).
 *   3. Resolve salary components in dependency / sequence order
 *      (fixed, percent, formula). Earnings build up GROSS, deductions reduce GROSS.
 *   4. Compute TAXABLE = GROSS minus components flagged !is_taxable.
 *   5. Apply BASE_BASE/BASE_GROSS taxes (e.g. CNSS on GROSS).
 *   6. Apply TAXABLE-stage modifiers, then progressive taxes (e.g. IR).
 *   7. Compute NET, then apply NET-stage modifiers (loans, garnishments).
 *   8. Append a SUMMARY line and return the result.
 */
final class PayrollEngine
{
    public function __construct(
        private readonly FormulaEngine $formula,
        private readonly TaxEngine $taxEngine,
        private readonly ModifierEngine $modifierEngine,
        private readonly PayrollAttendanceCalculator $attendanceCalculator,
    ) {
    }

    public function run(
        Employee $employee,
        string $period,
        bool $assumeUnrecordedAttendancePayable = false,
    ): PayrollResult
    {
        $this->assertPeriod($period);

        $employee->loadMissing(['company.country', 'company.companyTaxes', 'country']);
        $structure = $this->resolveStructure($employee, $period);

        if ($structure === null) {
            throw new PayrollException(
                "No active salary structure for employee #{$employee->id} in period {$period}"
            );
        }

        $structure->loadMissing(['lines.component']);

        [$start, $end] = $this->periodBoundaries($period);
        $contractualBase = (float) $structure->base_salary;
        $attendance = $this->attendanceCalculator->summarize(
            $employee,
            $start,
            $end,
            $contractualBase,
            $assumeUnrecordedAttendancePayable,
        );

        $context = new PayrollContext(
            employee: $employee,
            company: $employee->company,
            country: $employee->country ?? $employee->company->country,
            structure: $structure,
            period: $period,
            periodStart: $start,
            periodEnd: $end,
            baseSalary: $attendance['payable_salary'],
            currency: $structure->currency
                ?? $employee->currency
                ?? $employee->company->currency
                ?? $employee->company->country->currency,
        );

        $result = new PayrollResult();
        $result->currency = $context->currency;
        $result->countryCode = $context->country->code;
        $result->attendance = $attendance;
        $this->initialise($context, $result, $attendance);
        $result->trace('pipeline.start', ['period' => $period, 'employee_id' => $employee->id]);

        // 1) BASE-stage modifiers (e.g. proration, retro adjustments)
        $this->modifierEngine->applyForTarget($context, $result, 'BASE');
        $result->setVariable('BASE', $result->base);

        // 2) Salary components in dependency order
        $this->processComponents($context, $result);

        // 3) Pre-tax modifiers on GROSS (rare, but supported)
        $this->modifierEngine->applyForTarget($context, $result, 'GROSS');

        // 4) Initialise TAXABLE = GROSS adjusted by non-taxable earnings
        $result->taxable = $this->computeTaxableFromComponents($result);
        $result->setVariable('TAXABLE', $result->taxable);
        $result->trace('pipeline.taxable_initialised', ['taxable' => $result->taxable]);

        // 5) Run social-style taxes anchored on BASE / GROSS
        $this->taxEngine->applyForStage($context, $result, LegalTax::BASE_BASE);
        $this->taxEngine->applyForStage($context, $result, LegalTax::BASE_GROSS);

        // 6) Modifiers acting on TAXABLE (e.g. tax-deductible allowances)
        $this->modifierEngine->applyForTarget($context, $result, 'TAXABLE');

        // 7) Progressive / income taxes on TAXABLE (e.g. IR / IPR)
        $this->taxEngine->applyForStage($context, $result, LegalTax::BASE_TAXABLE);

        // 8) Final NET-stage modifiers (loans, garnishments, advances repayment)
        $result->setVariable('NET', $result->net);
        $this->modifierEngine->applyForTarget($context, $result, 'NET');
        $this->taxEngine->applyForStage($context, $result, LegalTax::BASE_NET);

        $this->finalise($result);

        return $result;
    }

    /**
     * @param  array<string, float>  $attendance
     */
    private function initialise(PayrollContext $context, PayrollResult $result, array $attendance): void
    {
        $payable = (float) $attendance['payable_salary'];
        $contractual = (float) $attendance['contractual_salary'];
        $workingDays = (float) $attendance['working_days'];
        $payableDays = (float) $attendance['payable_days'];

        $result->base = $payable;
        $result->gross = $payable;
        $result->taxable = $payable;
        $result->net = $payable;

        $result->variables['BASE_SALARY'] = $payable;
        $result->variables['CONTRACTUAL_BASE'] = $contractual;
        $result->variables['BASE'] = $result->base;
        $result->variables['GROSS'] = $result->gross;
        $result->variables['TAXABLE'] = $result->taxable;
        $result->variables['NET'] = $result->net;
        $result->variables['WORKING_DAYS'] = $workingDays;
        $result->variables['DAYS_WORKED'] = (float) $attendance['days_worked'];
        $result->variables['ABSENT_DAYS'] = (float) $attendance['absent_days'];
        $result->variables['RECORDED_ABSENT_DAYS'] = (float) $attendance['recorded_absent_days'];
        $result->variables['NO_SHOW_DAYS'] = (float) $attendance['no_show_days'];
        $result->variables['PENDING_ATTENDANCE_DAYS'] = (float) $attendance['pending_attendance_days'];
        $result->variables['LEAVE_DAYS'] = (float) $attendance['leave_days'];
        $result->variables['UNPAID_LEAVE_DAYS'] = (float) $attendance['unpaid_leave_days'];
        $result->variables['UNPAID_DAYS'] = (float) $attendance['unpaid_days'];
        $result->variables['PAYABLE_DAYS'] = $payableDays;
        $result->variables['OVERTIME_HOURS'] = (float) $attendance['overtime_hours'];
        $result->variables['DAILY_RATE'] = (float) $attendance['daily_rate'];

        $result->addLine(new PayrollLineDTO(
            kind: PayrollLine::KIND_COMPONENT,
            code: 'BASE_SALARY',
            label: 'Salaire de base',
            type: PayrollLine::TYPE_EARNING,
            baseAmount: $contractual,
            rate: $workingDays > 0 ? round($payableDays / $workingDays, 4) : 0.0,
            amount: $payable,
            isTaxable: true,
            affectsGross: true,
            affectsNet: true,
            sequence: 0,
            meta: ['kind' => 'base_salary'] + $attendance,
        ));
        $result->totalEarnings += $payable;

        $result->trace('pipeline.attendance', $attendance);
    }

    private function processComponents(PayrollContext $context, PayrollResult $result): void
    {
        $lines = $context->structure->lines
            ->filter(fn (EmployeeSalaryStructureLine $l) => $l->is_active && $l->component !== null)
            ->values();

        $ordered = $this->resolveExecutionOrder($lines);

        foreach ($ordered as $line) {
            $this->processSingleComponent($result, $line);
        }
    }

    /**
     * Topological sort by `depends_on` codes, then fall back to component sequence.
     *
     * @param Collection<int, EmployeeSalaryStructureLine> $lines
     * @return list<EmployeeSalaryStructureLine>
     */
    private function resolveExecutionOrder(Collection $lines): array
    {
        /** @var array<string, EmployeeSalaryStructureLine> $byCode */
        $byCode = [];
        foreach ($lines as $line) {
            $byCode[$line->component->code] = $line;
        }

        $seen = [];
        $stack = [];
        $output = [];

        $visit = function (EmployeeSalaryStructureLine $line) use (&$visit, &$seen, &$stack, &$output, $byCode): void {
            $code = $line->component->code;
            if (isset($seen[$code])) {
                return;
            }
            if (isset($stack[$code])) {
                throw new PayrollException("Cyclic dependency detected on component {$code}");
            }
            $stack[$code] = true;

            $deps = $line->component->depends_on ?? [];
            foreach ($deps as $dep) {
                if (isset($byCode[$dep])) {
                    $visit($byCode[$dep]);
                }
            }

            $stack[$code] = null;
            unset($stack[$code]);
            $seen[$code] = true;
            $output[] = $line;
        };

        $sorted = $lines->sortBy(fn (EmployeeSalaryStructureLine $l) => [
            $l->sequence,
            $l->component->sequence,
        ])->values();

        foreach ($sorted as $line) {
            $visit($line);
        }

        return $output;
    }

    private function processSingleComponent(PayrollResult $result, EmployeeSalaryStructureLine $line): void
    {
        $component = $line->component;

        // Le salaire de base est déjà posé dans initialise() via structure.base_salary.
        if (strtoupper((string) $component->code) === 'BASE_SALARY') {
            $result->trace('component.skipped.base_salary', [
                'component' => $component->code,
            ]);
            return;
        }

        $base = match ($component->base_target) {
            'BASE' => $result->base,
            'GROSS' => $result->gross,
            'TAXABLE' => $result->taxable,
            'NET' => $result->net,
            default => $result->base,
        };

        $rateUsed = 0.0;

        switch ($component->calculation_type) {
            case SalaryComponent::CALC_FIXED:
                $amount = $line->amount_override !== null
                    ? (float) $line->amount_override
                    : (float) $component->default_value;
                break;

            case SalaryComponent::CALC_PERCENT:
                $rate = $line->rate_override !== null
                    ? (float) $line->rate_override
                    : (float) ($line->amount_override ?? $component->default_value);
                $rateUsed = $rate;
                $amount = $base * $rate;
                break;

            case SalaryComponent::CALC_FORMULA:
                $expr = $line->formula_override ?: $component->formula;
                if ($expr === null || $expr === '') {
                    $amount = (float) ($line->amount_override ?? $component->default_value);
                } else {
                    $amount = $this->formula->evaluate($expr, $result->variables + [
                        'BASE' => $base,
                        'COMPONENT_DEFAULT' => (float) $component->default_value,
                    ]);
                }
                break;

            default:
                $result->trace('component.skipped.unknown_calc', [
                    'component' => $component->code,
                    'calculation_type' => $component->calculation_type,
                ]);
                return;
        }

        $amount = round($amount, 2);
        if ($amount === 0.0) {
            $result->trace('component.skipped.zero', ['component' => $component->code]);
            return;
        }

        $signed = $component->isDeduction() ? -abs($amount) : abs($amount);

        $result->addLine(new PayrollLineDTO(
            kind: PayrollLine::KIND_COMPONENT,
            code: $component->code,
            label: $component->name,
            type: $component->isEarning() ? PayrollLine::TYPE_EARNING : PayrollLine::TYPE_DEDUCTION,
            baseAmount: round($base, 2),
            rate: $rateUsed,
            amount: abs($amount),
            isTaxable: (bool) $component->is_taxable,
            affectsGross: (bool) $component->affects_gross,
            affectsNet: (bool) $component->affects_net,
            sequence: 100 + $component->sequence,
            sourceType: SalaryComponent::class,
            sourceId: $component->id,
            meta: [
                'calculation_type' => $component->calculation_type,
                'sign' => $component->isEarning() ? '+' : '-',
            ],
        ));

        if ($component->affects_gross) {
            $result->gross += $signed;
            $result->setVariable('GROSS', $result->gross);
        }
        if ($component->affects_net) {
            $result->net += $signed;
            $result->setVariable('NET', $result->net);
        }
        if ($component->isEarning()) {
            $result->totalEarnings += abs($amount);
        } else {
            $result->totalDeductions += abs($amount);
        }

        $result->setVariable($component->code, abs($amount));

        $result->trace('component.applied', [
            'component' => $component->code,
            'type' => $component->type,
            'amount' => $signed,
        ]);
    }

    private function computeTaxableFromComponents(PayrollResult $result): float
    {
        $taxable = 0.0;
        foreach ($result->lines as $line) {
            if ($line->kind !== PayrollLine::KIND_COMPONENT) {
                continue;
            }
            if (!$line->affectsGross) {
                continue;
            }
            $signed = $line->type === PayrollLine::TYPE_EARNING ? $line->amount : -$line->amount;
            if ($line->isTaxable) {
                $taxable += $signed;
            }
        }
        return round($taxable, 2);
    }

    private function finalise(PayrollResult $result): void
    {
        $result->base = round($result->base, 2);
        $result->gross = round($result->gross, 2);
        $result->taxable = round($result->taxable, 2);
        $result->net = round($result->net, 2);
        $result->totalEarnings = round($result->totalEarnings, 2);
        $result->totalDeductions = round($result->totalDeductions, 2);
        $result->totalEmployerCharges = round($result->totalEmployerCharges, 2);
        $result->totalEmployeeCharges = round($result->totalEmployeeCharges, 2);

        $result->addLine(new PayrollLineDTO(
            kind: PayrollLine::KIND_SUMMARY,
            code: 'SUMMARY',
            label: 'Récapitulatif',
            type: PayrollLine::TYPE_INFO,
            baseAmount: $result->gross,
            rate: 0.0,
            amount: $result->net,
            sequence: 9999,
            meta: $result->summary(),
        ));

        $result->trace('pipeline.end', $result->summary());
    }

    private function resolveStructure(Employee $employee, string $period): ?EmployeeSalaryStructure
    {
        [$start, $end] = $this->periodBoundaries($period);

        return EmployeeSalaryStructure::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeSalaryStructure::STATUS_ACTIVE)
            ->where('effective_from', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    private function assertPeriod(string $period): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new PayrollException("Invalid period '{$period}', expected format YYYY-MM");
        }
    }

    /**
     * @return array{0:string,1:string} [start, end] dates in Y-m-d
     */
    private function periodBoundaries(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        return [$start->toDateString(), $end->toDateString()];
    }
}
