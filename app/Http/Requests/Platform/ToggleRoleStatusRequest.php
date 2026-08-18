<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

class ToggleRoleStatusRequest extends SuperAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
