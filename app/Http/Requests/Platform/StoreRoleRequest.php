<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Domain\Platform\RolePermissionCatalog;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends SuperAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(RolePermissionCatalog::keys())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
