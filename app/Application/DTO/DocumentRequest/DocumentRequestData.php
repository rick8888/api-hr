<?php

declare(strict_types=1);

namespace App\Application\DTO\DocumentRequest;

final class DocumentRequestData
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $type,
        public readonly ?string $purpose = null,
        public readonly ?string $neededBy = null,
        public readonly string $status = 'pending',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = self::normalizeType((string) ($data['type'] ?? ''));

        return new self(
            employeeId: (int) ($data['employee_id'] ?? $data['employeeId'] ?? 0),
            type: $type,
            purpose: isset($data['purpose'])
                ? (string) $data['purpose']
                : (isset($data['remarks']) ? (string) $data['remarks'] : null),
            neededBy: isset($data['needed_by']) || isset($data['neededBy'])
                ? (string) ($data['needed_by'] ?? $data['neededBy'])
                : null,
            status: (string) ($data['status'] ?? 'pending'),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'type' => $this->type,
            'purpose' => $this->purpose,
            'needed_by' => $this->neededBy ?: null,
            'status' => $this->status,
        ];
    }

    public static function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        $aliases = [
            'employment_certificate' => 'employment_certificate',
            'salary_letter' => 'salary_letter',
            'experience_letter' => 'experience_letter',
            'no_objection_certificate_(noc)' => 'noc',
            'no_objection_certificate' => 'noc',
            'noc' => 'noc',
            'address_proof' => 'address_proof',
            'bank_letter' => 'bank_letter',
            'other' => 'other',
            'misc' => 'other',
            'miscellaneous' => 'other',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}
