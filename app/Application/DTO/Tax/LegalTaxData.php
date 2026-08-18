<?php

declare(strict_types=1);

namespace App\Application\DTO\Tax;

use App\Domain\Tax\Models\LegalTax;

final class LegalTaxData
{
    /** @param array<int, array<string, mixed>> $brackets */
    public function __construct(
        public readonly int $countryId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly string $category,
        public readonly string $baseTarget,
        public readonly ?string $description = null,
        public readonly float $employerRate = 0,
        public readonly float $employeeRate = 0,
        public readonly ?float $ceiling = null,
        public readonly ?float $floor = null,
        public readonly ?string $formula = null,
        public readonly bool $reducesTaxable = false,
        public readonly bool $isActive = true,
        public readonly int $sequence = 100,
        public readonly ?string $effectiveFrom = null,
        public readonly ?string $effectiveTo = null,
        public readonly array $brackets = [],
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
            'category' => $this->category,
            'type' => $this->type,
            'base_target' => $this->baseTarget,
            'employer_rate' => $this->employerRate,
            'employee_rate' => $this->employeeRate,
            'ceiling' => $this->ceiling,
            'floor' => $this->floor,
            'formula' => $this->formula,
            'reduces_taxable' => $this->reducesTaxable,
            'is_active' => $this->isActive,
            'sequence' => $this->sequence,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            countryId: (int) $data['country_id'],
            code: (string) $data['code'],
            name: (string) $data['name'],
            type: (string) $data['type'],
            category: (string) ($data['category'] ?? LegalTax::CATEGORY_SOCIAL),
            baseTarget: (string) $data['base_target'],
            description: $data['description'] ?? null,
            employerRate: (float) ($data['employer_rate'] ?? 0),
            employeeRate: (float) ($data['employee_rate'] ?? 0),
            ceiling: isset($data['ceiling']) ? (float) $data['ceiling'] : null,
            floor: isset($data['floor']) ? (float) $data['floor'] : null,
            formula: $data['formula'] ?? null,
            reducesTaxable: (bool) ($data['reduces_taxable'] ?? false),
            isActive: (bool) ($data['is_active'] ?? true),
            sequence: (int) ($data['sequence'] ?? 100),
            effectiveFrom: $data['effective_from'] ?? null,
            effectiveTo: $data['effective_to'] ?? null,
            brackets: $data['brackets'] ?? [],
        );
    }
}
