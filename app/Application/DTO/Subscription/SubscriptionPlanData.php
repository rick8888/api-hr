<?php

declare(strict_types=1);

namespace App\Application\DTO\Subscription;

final class SubscriptionPlanData
{
    /** @param array<int, string> $includedServices
     *  @param array<string, float|int|string> $currencyPrices
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $tagline = null,
        public readonly float $priceMonthly = 0,
        public readonly string $currency = 'EUR',
        public readonly array $currencyPrices = [],
        public readonly ?int $maxUsers = null,
        public readonly ?int $maxEmployees = null,
        public readonly ?int $storageGb = null,
        public readonly bool $isActive = true,
        public readonly bool $isHighlighted = false,
        public readonly bool $isFree = false,
        public readonly ?int $sortOrder = null,
        public readonly array $includedServices = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        $code = $this->normalizeCode($this->code !== '' ? $this->code : $this->name);

        return [
            'code' => $code,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'price_monthly' => $this->priceMonthly,
            'currency' => strtoupper($this->currency),
            'currency_prices' => self::normalizeCurrencyPrices($this->currencyPrices),
            'max_users' => $this->maxUsers,
            'max_employees' => $this->maxEmployees,
            'storage_gb' => $this->storageGb,
            'is_active' => $this->isActive,
            'is_highlighted' => $this->isHighlighted,
            'is_free' => $this->isFree,
            'sort_order' => $this->sortOrder,
            'included_services' => $this->includedServices,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            tagline: $data['tagline'] ?? null,
            priceMonthly: (float) ($data['price_monthly'] ?? 0),
            currency: (string) ($data['currency'] ?? 'EUR'),
            currencyPrices: is_array($data['currency_prices'] ?? null) ? $data['currency_prices'] : [],
            maxUsers: array_key_exists('max_users', $data) ? self::nullableInt($data['max_users']) : null,
            maxEmployees: array_key_exists('max_employees', $data) ? self::nullableInt($data['max_employees']) : null,
            storageGb: array_key_exists('storage_gb', $data) ? self::nullableInt($data['storage_gb']) : null,
            isActive: (bool) ($data['is_active'] ?? true),
            isHighlighted: (bool) ($data['is_highlighted'] ?? false),
            isFree: (bool) ($data['is_free'] ?? false),
            sortOrder: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            includedServices: array_values($data['included_services'] ?? []),
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function normalizeCode(string $code): string
    {
        $normalized = strtolower(trim($code));
        $normalized = (string) preg_replace('/[^a-z0-9_]+/', '_', $normalized);

        return trim($normalized, '_');
    }

    /** @param array<string, float|int|string> $prices
     *  @return array<string, float>
     */
    private static function normalizeCurrencyPrices(array $prices): array
    {
        $normalized = [];
        foreach ($prices as $currency => $amount) {
            $code = strtoupper(trim((string) $currency));
            if (preg_match('/^[A-Z]{3}$/', $code) === 1 && is_numeric($amount) && (float) $amount >= 0) {
                $normalized[$code] = (float) $amount;
            }
        }

        return $normalized;
    }
}
