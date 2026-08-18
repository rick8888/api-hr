<?php

declare(strict_types=1);

namespace App\Application\DTO\Organization;

final class SiteData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?int $countryId = null,
        public readonly ?string $city = null,
        public readonly ?string $address = null,
        public readonly ?string $description = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        $payload = [
            'name' => $this->name,
            'country_id' => $this->countryId,
            'city' => $this->city,
            'address' => $this->address,
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
        $countryId = $data['country_id'] ?? $data['countryId'] ?? null;

        return new self(
            name: (string) $data['name'],
            code: isset($data['code']) && $data['code'] !== '' ? (string) $data['code'] : null,
            countryId: $countryId !== null && $countryId !== '' ? (int) $countryId : null,
            city: isset($data['city']) && $data['city'] !== '' ? (string) $data['city'] : null,
            address: $data['address'] ?? null,
            description: $data['description'] ?? null,
            isActive: (bool) ($data['active'] ?? $data['is_active'] ?? true),
        );
    }
}
