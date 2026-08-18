<?php

declare(strict_types=1);

namespace App\Application\DTO\Organization;

final class DesignationData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?int $departmentId = null,
        public readonly ?string $description = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        $payload = [
            'name' => $this->name,
            'department_id' => $this->departmentId,
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
        $departmentId = $data['department_id'] ?? $data['departmentId'] ?? null;

        return new self(
            name: (string) $data['name'],
            code: isset($data['code']) && $data['code'] !== '' ? (string) $data['code'] : null,
            departmentId: $departmentId !== null && $departmentId !== '' ? (int) $departmentId : null,
            description: $data['description'] ?? null,
            isActive: (bool) ($data['active'] ?? $data['is_active'] ?? true),
        );
    }
}
