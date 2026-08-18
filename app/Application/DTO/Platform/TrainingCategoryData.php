<?php

declare(strict_types=1);

namespace App\Application\DTO\Platform;

final readonly class TrainingCategoryData
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description,
        public int $sortOrder = 0,
        public bool $isSystem = false,
        public bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            code: (string) $data['code'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            sortOrder: (int) ($data['sort_order'] ?? $data['sortOrder'] ?? 0),
            isSystem: (bool) ($data['is_system'] ?? $data['isSystem'] ?? false),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? $data['active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
            'is_system' => $this->isSystem,
            'is_active' => $this->isActive,
        ];
    }
}
