<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\EntityStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Throwable;

final class EntityStatusCatalog
{
    /**
     * Codes actifs du catalogue, ou fallback (constantes métier) si la table est vide.
     *
     * @param  list<string>  $fallback
     * @return list<string>
     */
    public static function codes(string $entityType, array $fallback = []): array
    {
        try {
            $codes = EntityStatus::query()
                ->where('entity_type', $entityType)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('code')
                ->map(fn ($code) => (string) $code)
                ->values()
                ->all();

            return $codes !== [] ? $codes : array_values($fallback);
        } catch (Throwable) {
            return array_values($fallback);
        }
    }

    /**
     * @param  list<string>  $fallback
     */
    public static function contains(string $entityType, string $code, array $fallback = []): bool
    {
        return in_array($code, self::codes($entityType, $fallback), true);
    }

    /**
     * @param  list<string>  $fallback
     */
    public static function rule(string $entityType, array $fallback = []): In
    {
        return Rule::in(self::codes($entityType, $fallback));
    }
}
