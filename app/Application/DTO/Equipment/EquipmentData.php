<?php

declare(strict_types=1);

namespace App\Application\DTO\Equipment;

use App\Domain\Equipment\Models\Equipment;

final class EquipmentData
{
    /** @param list<string>|null $specifications */
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly ?string $description = null,
        public readonly int $stock = 0,
        public readonly ?string $brand = null,
        public readonly ?string $model = null,
        public readonly ?array $specifications = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $specs = $data['specifications'] ?? null;
        if (is_string($specs)) {
            $decoded = json_decode($specs, true);
            $specs = is_array($decoded) ? $decoded : null;
        }

        return new self(
            name: (string) ($data['name'] ?? ''),
            category: self::normalizeCategory((string) ($data['category'] ?? '')),
            description: isset($data['description']) ? (string) $data['description'] : null,
            stock: (int) ($data['stock'] ?? 0),
            brand: isset($data['brand']) ? (string) $data['brand'] : null,
            model: isset($data['model']) ? (string) $data['model'] : null,
            specifications: is_array($specs)
                ? array_values(array_filter(array_map('strval', $specs)))
                : null,
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
            'stock' => max(0, $this->stock),
            'brand' => $this->brand,
            'model' => $this->model,
            'specifications' => $this->specifications,
            'is_active' => $this->isActive,
        ];
    }

    public static function normalizeCategory(string $category): string
    {
        $normalized = strtolower(trim($category));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        $aliases = [
            'laptop' => 'laptop',
            'ordinateurs_portables' => 'laptop',
            'ordinateur_portable' => 'laptop',
            'desktop' => 'desktop',
            'ordinateurs_de_bureau' => 'desktop',
            'ordinateur_de_bureau' => 'desktop',
            'monitor' => 'monitor',
            'ecran' => 'monitor',
            'écrans' => 'monitor',
            'phone' => 'phone',
            'telephone' => 'phone',
            'téléphone' => 'phone',
            'tablet' => 'tablet',
            'tablette' => 'tablet',
            'accessories' => 'accessories',
            'accessoires' => 'accessories',
            'furniture' => 'furniture',
            'mobilier' => 'furniture',
            'other' => 'other',
            'autre' => 'other',
        ];

        $resolved = $aliases[$normalized] ?? $normalized;

        return in_array($resolved, Equipment::CATEGORIES, true) ? $resolved : $resolved;
    }
}
