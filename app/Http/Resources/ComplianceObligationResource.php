<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Compliance\Models\ComplianceObligation */
class ComplianceObligationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country', fn () => $this->country->name),
            'country_code' => $this->whenLoaded('country', fn () => $this->country->code),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'authority' => $this->authority,
            'frequency' => $this->frequency,
            'reference_period' => $this->reference_period,
            'due_rule' => $this->due_rule,
            'due_rule_label' => $this->formatDueRuleLabel(),
            'deadline_day_of_month' => $this->deadline_day_of_month,
            'grace_days' => $this->grace_days,
            'effective_from' => optional($this->effective_from)->toDateString(),
            'is_active' => (bool) $this->is_active,
            'settings' => $this->settings ?? [],
            'linked_rule_codes' => $this->settings['linked_rule_codes'] ?? [],
            'filing_channel' => $this->settings['filing_channel'] ?? null,
            'is_mandatory' => (bool) ($this->settings['is_mandatory'] ?? false),
        ];
    }

    private function formatDueRuleLabel(): string
    {
        $rule = $this->due_rule;

        if (! is_array($rule)) {
            return "Jour {$this->deadline_day_of_month}";
        }

        return match ($rule['type'] ?? '') {
            'fixed_day_next_month' => 'Le '.($rule['day'] ?? $this->deadline_day_of_month).' du mois suivant',
            'fixed_day' => sprintf(
                'Le %02d/%02d',
                (int) ($rule['day'] ?? 1),
                (int) ($rule['month'] ?? 1),
            ),
            'end_of_month_next_month' => 'Fin du mois suivant',
            default => "Jour {$this->deadline_day_of_month}",
        };
    }
}
