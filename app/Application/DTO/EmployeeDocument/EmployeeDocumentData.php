<?php

declare(strict_types=1);

namespace App\Application\DTO\EmployeeDocument;

final class EmployeeDocumentData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly string $category,
        public readonly ?string $reference = null,
        public readonly ?string $issueDate = null,
        public readonly ?string $expiryDate = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $category = strtolower(str_replace([' ', '-'], '_', (string) ($data['category'] ?? '')));

        return new self(
            employeeId: (int) ($data['employee_id'] ?? $data['employeeId'] ?? 0),
            name: trim((string) ($data['name'] ?? '')),
            category: $category,
            reference: self::nullableString($data['reference'] ?? null),
            issueDate: self::nullableDate($data['issue_date'] ?? $data['issueDate'] ?? null),
            expiryDate: self::nullableDate($data['expiry_date'] ?? $data['expiryDate'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'name' => $this->name,
            'category' => $this->category,
            'reference' => $this->reference,
            'issue_date' => $this->issueDate,
            'expiry_date' => $this->expiryDate,
        ];
    }

    public static function normalizeCategory(string $value): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', $value));
        $aliases = [
            'passport' => 'passport',
            'work_visa' => 'work_visa',
            'workvisa' => 'work_visa',
            'employment_contract' => 'employment_contract',
            'employmentcontract' => 'employment_contract',
            'driving_license' => 'driving_license',
            'drivers_license' => 'driving_license',
            'driving_licence' => 'driving_license',
            'national_id' => 'national_id',
            'nationalid' => 'national_id',
            'other' => 'other',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
