<?php

declare(strict_types=1);

namespace App\Application\DTO\Platform;

final readonly class EntityStatusData
{
    public function __construct(
        public string $entityType,
        public string $code,
        public string $name,
        public ?string $nameEn,
        public ?string $description,
        public string $tone = 'neutral',
        public int $sortOrder = 0,
        public bool $isSystem = false,
        public bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tone = (string) ($data['tone'] ?? 'neutral');

        return new self(
            entityType: (string) ($data['entity_type'] ?? $data['entityType'] ?? ''),
            code: (string) $data['code'],
            name: (string) $data['name'],
            nameEn: isset($data['name_en']) || isset($data['nameEn'])
                ? (string) ($data['name_en'] ?? $data['nameEn'])
                : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            tone: $tone !== '' ? $tone : 'neutral',
            sortOrder: (int) ($data['sort_order'] ?? $data['sortOrder'] ?? 0),
            isSystem: (bool) ($data['is_system'] ?? $data['isSystem'] ?? false),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? $data['active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'code' => $this->code,
            'name' => $this->name,
            'name_en' => $this->nameEn,
            'description' => $this->description,
            'tone' => $this->tone,
            'sort_order' => $this->sortOrder,
            'is_system' => $this->isSystem,
            'is_active' => $this->isActive,
        ];
    }
}
