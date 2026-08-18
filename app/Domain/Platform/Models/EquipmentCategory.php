<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

class EquipmentCategory extends Model
{
    public const CODES = [
        'laptop',
        'desktop',
        'monitor',
        'phone',
        'tablet',
        'accessories',
        'furniture',
        'other',
    ];

    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function codeRule(bool $activeOnly = true): Exists|In
    {
        if (! Schema::hasTable('equipment_categories')) {
            return Rule::in(self::CODES);
        }

        $rule = Rule::exists('equipment_categories', 'code');

        if ($activeOnly) {
            $rule->where('is_active', true);
        }

        return $rule;
    }
}
