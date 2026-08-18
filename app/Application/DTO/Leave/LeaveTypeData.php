<?php

declare(strict_types=1);

namespace App\Application\DTO\Leave;

final class LeaveTypeData
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $description = null,
        public readonly bool $proofRequired = false,
        public readonly bool $isCompensatoryOffType = false,
        public readonly bool $enableAutomaticAccrual = false,
        public readonly ?string $accrualFrequency = null,
        public readonly ?float $accrualRate = null,
        public readonly ?int $maxAccrualLimit = null,
        public readonly bool $allowCarryForward = false,
        public readonly ?int $maxCarryForwardDays = null,
        public readonly ?int $carryForwardExpiryMonths = null,
        public readonly bool $allowEncashment = false,
        public readonly ?int $maxEncashmentDays = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $enableAccrual = (bool) ($data['enable_automatic_accrual'] ?? $data['enableAutomaticAccrual'] ?? false);
        $allowCarry = (bool) ($data['allow_carry_forward'] ?? $data['allowCarryForward'] ?? false);
        $allowEncash = (bool) ($data['allow_encashment'] ?? $data['allowEncashment'] ?? false);

        return new self(
            name: (string) $data['name'],
            code: strtoupper((string) $data['code']),
            description: self::nullableString($data['description'] ?? null),
            proofRequired: (bool) ($data['proof_required'] ?? $data['proofRequired'] ?? false),
            isCompensatoryOffType: (bool) ($data['is_compensatory_off_type'] ?? $data['isCompensatoryOffType'] ?? false),
            enableAutomaticAccrual: $enableAccrual,
            accrualFrequency: $enableAccrual
                ? self::nullableString($data['accrual_frequency'] ?? $data['accrualFrequency'] ?? null)
                : null,
            accrualRate: $enableAccrual ? self::nullableFloat($data['accrual_rate'] ?? $data['accrualRate'] ?? null) : null,
            maxAccrualLimit: $enableAccrual ? self::nullableInt($data['max_accrual_limit'] ?? $data['maxAccrualLimit'] ?? null) : null,
            allowCarryForward: $allowCarry,
            maxCarryForwardDays: $allowCarry
                ? self::nullableInt($data['max_carry_forward_days'] ?? $data['maxCarryForwardDays'] ?? null)
                : null,
            carryForwardExpiryMonths: $allowCarry
                ? self::nullableInt($data['carry_forward_expiry_months'] ?? $data['carryForwardExpiryMonths'] ?? null)
                : null,
            allowEncashment: $allowEncash,
            maxEncashmentDays: $allowEncash
                ? self::nullableInt($data['max_encashment_days'] ?? $data['maxEncashmentDays'] ?? null)
                : null,
            isActive: (bool) ($data['active'] ?? $data['is_active'] ?? $data['isActive'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'proof_required' => $this->proofRequired,
            'is_compensatory_off_type' => $this->isCompensatoryOffType,
            'enable_automatic_accrual' => $this->enableAutomaticAccrual,
            'accrual_frequency' => $this->accrualFrequency,
            'accrual_rate' => $this->accrualRate,
            'max_accrual_limit' => $this->maxAccrualLimit,
            'allow_carry_forward' => $this->allowCarryForward,
            'max_carry_forward_days' => $this->maxCarryForwardDays,
            'carry_forward_expiry_months' => $this->carryForwardExpiryMonths,
            'allow_encashment' => $this->allowEncashment,
            'max_encashment_days' => $this->maxEncashmentDays,
            'is_active' => $this->isActive,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
