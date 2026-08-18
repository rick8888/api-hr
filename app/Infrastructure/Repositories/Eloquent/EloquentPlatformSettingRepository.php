<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Platform\Repositories\PlatformSettingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentPlatformSettingRepository implements PlatformSettingRepositoryInterface
{
    public function listByGroup(?string $group = null): Collection
    {
        $query = PlatformSetting::query()->orderBy('group')->orderBy('key');

        if ($group !== null && $group !== '') {
            $query->where('group', $group);
        }

        return $query->get();
    }

    public function upsert(string $group, string $key, array $value, ?string $label = null): PlatformSetting
    {
        return PlatformSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'label' => $label],
        );
    }
}
