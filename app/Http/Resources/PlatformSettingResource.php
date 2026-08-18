<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Platform\Models\PlatformSetting */
class PlatformSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value ?? [],
            'label' => $this->label,
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
