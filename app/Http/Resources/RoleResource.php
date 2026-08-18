<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Platform\Models\Role */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $usersCount = app(RoleService::class)->usersCountForRole($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'permissions' => $this->permissions ?? [],
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'users_count' => $usersCount,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
