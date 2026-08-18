<?php

declare(strict_types=1);

namespace App\Application\DTO\Training;

use App\Domain\Training\Models\Training;

final class TrainingData
{
    /**
     * @param  list<string>|null  $objectives
     */
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly ?string $description = null,
        public readonly ?string $duration = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?array $objectives = null,
        public readonly string $status = 'published',
        public readonly bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $category = self::normalizeCategory((string) ($data['category'] ?? ''));
        $objectives = $data['objectives'] ?? null;
        if (is_string($objectives)) {
            $decoded = json_decode($objectives, true);
            $objectives = is_array($decoded) ? $decoded : null;
        }

        return new self(
            name: (string) ($data['name'] ?? ''),
            category: $category,
            description: isset($data['description']) ? (string) $data['description'] : null,
            duration: isset($data['duration']) ? (string) $data['duration'] : null,
            startDate: isset($data['start_date']) || isset($data['startDate'])
                ? (string) ($data['start_date'] ?? $data['startDate'])
                : null,
            endDate: isset($data['end_date']) || isset($data['endDate'])
                ? (string) ($data['end_date'] ?? $data['endDate'])
                : null,
            objectives: is_array($objectives)
                ? array_values(array_filter(array_map('strval', $objectives)))
                : null,
            status: (string) ($data['status'] ?? 'published'),
            isActive: array_key_exists('is_active', $data) || array_key_exists('active', $data) || array_key_exists('isActive', $data)
                ? (bool) ($data['is_active'] ?? $data['active'] ?? $data['isActive'])
                : true,
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'duration' => $this->duration,
            'start_date' => $this->startDate ?: null,
            'end_date' => $this->endDate ?: null,
            'objectives' => $this->objectives,
            'status' => $this->status,
            'is_active' => $this->isActive,
        ];
    }

    public static function normalizeCategory(string $category): string
    {
        $normalized = strtolower(trim($category));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        $aliases = [
            'technique' => 'technical',
            'technical' => 'technical',
            'management' => 'management',
            'soft_skills' => 'soft_skills',
            'compétences_comportementales' => 'soft_skills',
            'competences_comportementales' => 'soft_skills',
            'sales' => 'sales',
            'ventes' => 'sales',
            'compliance' => 'compliance',
            'conformite' => 'compliance',
            'conformité' => 'compliance',
        ];

        $resolved = $aliases[$normalized] ?? $normalized;

        return in_array($resolved, Training::CATEGORIES, true) ? $resolved : $resolved;
    }
}
