<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

class UpdatePlatformSettingsRequest extends SuperAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.group' => ['required', 'string', 'max:40'],
            'settings.*.key' => ['required', 'string', 'max:80'],
            'settings.*.value' => ['required', 'array'],
            'settings.*.label' => ['nullable', 'string', 'max:160'],
        ];
    }
}
