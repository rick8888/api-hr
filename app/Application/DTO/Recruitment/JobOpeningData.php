<?php

declare(strict_types=1);

namespace App\Application\DTO\Recruitment;

use App\Domain\Recruitment\Models\JobOpening;

final class JobOpeningData
{
    public function __construct(
        public readonly int $designationId,
        public readonly ?string $title = null,
        public readonly ?string $department = null,
        public readonly ?string $location = null,
        public readonly string $type = 'full_time',
        public readonly string $status = 'open',
        public readonly ?string $description = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $designationId = $data['designation_id'] ?? $data['designationId'] ?? null;

        return new self(
            designationId: (int) $designationId,
            title: isset($data['title']) ? (string) $data['title'] : null,
            department: isset($data['department']) ? (string) $data['department'] : null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            type: self::normalizeType((string) ($data['type'] ?? 'full_time')),
            status: self::normalizeStatus((string) ($data['status'] ?? 'open')),
            description: isset($data['description']) ? (string) $data['description'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'designation_id' => $this->designationId,
            'title' => $this->title,
            'department' => $this->department,
            'location' => $this->location,
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
        ];
    }

    public static function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        $aliases = [
            'full_time' => 'full_time',
            'fulltime' => 'full_time',
            'part_time' => 'part_time',
            'parttime' => 'part_time',
            'contract' => 'contract',
            'contrat' => 'contract',
        ];

        $resolved = $aliases[$normalized] ?? $normalized;

        return in_array($resolved, JobOpening::TYPES, true) ? $resolved : $resolved;
    }

    public static function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, JobOpening::STATUSES, true) ? $normalized : $normalized;
    }

    public static function typeLabel(string $type): string
    {
        return match (self::normalizeType($type)) {
            'part_time' => 'Part-time',
            'contract' => 'Contract',
            default => 'Full-time',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match (self::normalizeStatus($status)) {
            'closed' => 'Closed',
            default => 'Open',
        };
    }
}
