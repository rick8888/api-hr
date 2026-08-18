<?php

declare(strict_types=1);

namespace App\Application\DTO\Compliance;

final class ComplianceObligationData
{
    /** @param array<string, mixed>|null $dueRule */
    /** @param array<string, mixed>|null $settings */
    public function __construct(
        public readonly int $countryId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly string $frequency,
        public readonly string $referencePeriod,
        public readonly ?string $description = null,
        public readonly ?string $authority = null,
        public readonly ?array $dueRule = null,
        public readonly int $deadlineDayOfMonth = 15,
        public readonly int $graceDays = 0,
        public readonly ?string $effectiveFrom = null,
        public readonly bool $isActive = true,
        public readonly ?array $settings = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'country_id' => $this->countryId,
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'authority' => $this->authority,
            'frequency' => $this->frequency,
            'reference_period' => $this->referencePeriod,
            'due_rule' => $this->dueRule,
            'deadline_day_of_month' => $this->deadlineDayOfMonth,
            'grace_days' => $this->graceDays,
            'effective_from' => $this->effectiveFrom,
            'is_active' => $this->isActive,
            'settings' => $this->settings,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $dueRule = $data['due_rule'] ?? null;
        $deadlineDay = self::resolveDeadlineDay($dueRule, (int) ($data['deadline_day_of_month'] ?? 15));

        return new self(
            countryId: (int) $data['country_id'],
            code: (string) $data['code'],
            name: (string) $data['name'],
            type: (string) ($data['type'] ?? 'social'),
            frequency: (string) ($data['frequency'] ?? 'monthly'),
            referencePeriod: (string) ($data['reference_period'] ?? 'previous_month'),
            description: $data['description'] ?? null,
            authority: $data['authority'] ?? null,
            dueRule: $dueRule,
            deadlineDayOfMonth: $deadlineDay,
            graceDays: (int) ($data['grace_days'] ?? 0),
            effectiveFrom: $data['effective_from'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            settings: $data['settings'] ?? null,
        );
    }

    /** @param array<string, mixed>|null $dueRule */
    private static function resolveDeadlineDay(?array $dueRule, int $fallback): int
    {
        if ($dueRule === null) {
            return $fallback;
        }

        if (($dueRule['type'] ?? '') === 'fixed_day_next_month' && isset($dueRule['day'])) {
            return (int) $dueRule['day'];
        }

        if (($dueRule['type'] ?? '') === 'fixed_day' && isset($dueRule['day'])) {
            return (int) $dueRule['day'];
        }

        return $fallback;
    }
}
