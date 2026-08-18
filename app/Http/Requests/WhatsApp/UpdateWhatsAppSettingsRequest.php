<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'attendance_enabled' => ['sometimes', 'boolean'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:40'],
            'api_version' => ['sometimes', 'nullable', 'string', 'max:20'],
            'phone_number_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'business_account_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'display_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'verify_token' => ['sometimes', 'nullable', 'string', 'max:120'],
            'access_token' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'app_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
