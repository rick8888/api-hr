<?php

declare(strict_types=1);

namespace App\Application\DTO\Organization;

final class DepartmentData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?int $parentDepartmentId = null,
        public readonly ?string $description = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        $payload = [
            'name' => $this->name,
            'parent_department_id' => $this->parentDepartmentId,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];

        if ($this->code !== null && $this->code !== '') {
            $payload['code'] = strtoupper($this->code);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $parentId = $data['parent_department_id'] ?? $data['parentId'] ?? null;

        return new self(
            name: (string) $data['name'],
            code: isset($data['code']) && $data['code'] !== '' ? (string) $data['code'] : null,
            parentDepartmentId: $parentId !== null && $parentId !== '' ? (int) $parentId : null,
            description: $data['description'] ?? null,
            isActive: (bool) ($data['active'] ?? $data['is_active'] ?? true),
        );
    }
}
