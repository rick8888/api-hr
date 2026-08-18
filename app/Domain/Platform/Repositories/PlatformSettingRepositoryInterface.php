<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Collection;

interface PlatformSettingRepositoryInterface
{
    /** @return Collection<int, PlatformSetting> */
    public function listByGroup(?string $group = null): Collection;

    /** @param array<string, mixed> $value */
    public function upsert(string $group, string $key, array $value, ?string $label = null): PlatformSetting;
}
