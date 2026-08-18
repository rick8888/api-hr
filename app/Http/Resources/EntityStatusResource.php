<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Platform\Models\EntityStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EntityStatus */
class EntityStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_label' => EntityStatus::entityLabel((string) $this->entity_type),
            'code' => $this->code,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'tone' => $this->tone,
            'sort_order' => (int) $this->sort_order,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
