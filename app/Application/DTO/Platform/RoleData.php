<?php

declare(strict_types=1);

namespace App\Application\DTO\Platform;

final readonly class RoleData
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public array $permissions,
        public bool $isSystem = false,
        public bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            permissions: array_values($data['permissions'] ?? []),
            isSystem: (bool) ($data['is_system'] ?? $data['isSystem'] ?? false),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? $data['active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'permissions' => $this->permissions,
            'is_system' => $this->isSystem,
            'is_active' => $this->isActive,
        ];
    }
}
