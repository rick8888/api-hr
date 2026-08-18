<?php

declare(strict_types=1);

namespace App\Engines;

use App\Application\DTO\Payroll\PayrollContext;
use App\Application\DTO\Payroll\PayrollLineDTO;
use App\Application\DTO\Payroll\PayrollResult;
use App\Domain\Payroll\Models\PayrollLine;
use App\Domain\Payroll\Models\PayrollModifier;

/**
 * ModifierEngine
 *
 * Applies free-form payroll modifiers (bonuses, advances, garnishments, retro pay,
 * etc.) on top of the running totals at well-defined stages: BASE, GROSS, TAXABLE, NET.
 *
 * Each modifier can be:
 *   - fixed   : an absolute amount
 *   - percent : a percentage of the targeted total
 *   - formula : evaluated by FormulaEngine with all current variables in scope
 *
 * A modifier may be global (company-wide) or attached to a specific employee.
 */
final class ModifierEngine
{
    public function __construct(private readonly FormulaEngine $formula)
    {
    }

    public function applyForTarget(PayrollContext $context, PayrollResult $result, string $target): void
    {
        $modifiers = $this->loadModifiers($context, $target);

        foreach ($modifiers as $modifier) {
            $this->applyModifier($context, $result, $modifier);
        }
    }

    /**
     * @return iterable<PayrollModifier>
     */
    private function loadModifiers(PayrollContext $context, string $target): iterable
    {
        return PayrollModifier::query()
            ->where('is_active', true)
            ->where('target', $target)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($context) {
                $q->where(function ($q2) use ($context) {
                    $q2->where('scope', PayrollModifier::SCOPE_GLOBAL)
                        ->where(function ($q3) use ($context) {
                            $q3->whereNull('company_id')
                                ->orWhere('company_id', $context->company->id);
                        });
                })->orWhere(function ($q2) use ($context) {
                    $q2->where('scope', PayrollModifier::SCOPE_EMPLOYEE)
                        ->where('employee_id', $context->employee->id);
                });
            })
            ->orderBy('sequence')
            ->get()
            ->filter(fn (PayrollModifier $m) => $m->appliesToPeriod($context->period))
            ->values();
    }

    private function applyModifier(PayrollContext $context, PayrollResult $result, PayrollModifier $modifier): void
    {
        $base = $this->resolveBase($modifier->target, $result);

        $rawAmount = match ($modifier->type) {
            PayrollModifier::TYPE_FIXED   => (float) $modifier->value,
            PayrollModifier::TYPE_PERCENT => $base * (float) $modifier->value,
            PayrollModifier::TYPE_FORMULA => $modifier->formula
                ? $this->formula->evaluate($modifier->formula, $result->variables + ['BASE' => $base])
                : 0.0,
            default => 0.0,
        };

        $sign = $modifier->sign === '-' ? -1 : 1;
        $amount = round($sign * $rawAmount, 2);

        if ($amount === 0.0) {
            $result->trace('modifier.skipped.zero', [
                'modifier' => $modifier->code,
                'target' => $modifier->target,
            ]);
            return;
        }

        $isEarning = $amount > 0;
        $abs = abs($amount);

        $line = new PayrollLineDTO(
            kind: PayrollLine::KIND_MODIFIER,
            code: $modifier->code,
            label: $modifier->name,
            type: $isEarning ? PayrollLine::TYPE_EARNING : PayrollLine::TYPE_DEDUCTION,
            baseAmount: round($base, 2),
            rate: $modifier->type === PayrollModifier::TYPE_PERCENT ? (float) $modifier->value : 0.0,
            amount: $abs,
            isTaxable: false,
            affectsGross: in_array($modifier->target, [PayrollModifier::TARGET_BASE, PayrollModifier::TARGET_GROSS], true),
            affectsNet: $modifier->target === PayrollModifier::TARGET_NET,
            sequence: 700 + $modifier->sequence,
            sourceType: PayrollModifier::class,
            sourceId: $modifier->id,
            meta: [
                'scope' => $modifier->scope,
                'target' => $modifier->target,
                'sign' => $modifier->sign,
                'type' => $modifier->type,
            ],
        );

        $result->addLine($line);

        switch ($modifier->target) {
            case PayrollModifier::TARGET_BASE:
                $result->base += $amount;
                $result->setVariable('BASE', $result->base);
                break;

            case PayrollModifier::TARGET_GROSS:
                $result->gross += $amount;
                $result->setVariable('GROSS', $result->gross);
                if ($isEarning) {
                    $result->totalEarnings += $abs;
                } else {
                    $result->totalDeductions += $abs;
                }
                break;

            case PayrollModifier::TARGET_TAXABLE:
                $result->taxable += $amount;
                $result->setVariable('TAXABLE', $result->taxable);
                break;

            case PayrollModifier::TARGET_NET:
                $result->net += $amount;
                $result->setVariable('NET', $result->net);
                if ($isEarning) {
                    $result->totalEarnings += $abs;
                } else {
                    $result->totalDeductions += $abs;
                }
                break;
        }

        $result->trace('modifier.applied', [
            'modifier' => $modifier->code,
            'target' => $modifier->target,
            'type' => $modifier->type,
            'amount' => $amount,
        ]);
    }

    private function resolveBase(string $target, PayrollResult $result): float
    {
        return match ($target) {
            PayrollModifier::TARGET_BASE    => $result->base,
            PayrollModifier::TARGET_GROSS   => $result->gross,
            PayrollModifier::TARGET_TAXABLE => $result->taxable,
            PayrollModifier::TARGET_NET     => $result->net,
            default => 0.0,
        };
    }
}
