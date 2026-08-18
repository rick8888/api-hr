<?php

declare(strict_types=1);

namespace App\Http\Requests\SelfService;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSelfServicePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('currentPassword') && ! $this->has('current_password')) {
            $merge['current_password'] = $this->input('currentPassword');
        }
        if ($this->has('newPassword') && ! $this->has('password')) {
            $merge['password'] = $this->input('newPassword');
        }
        if ($this->has('confirmPassword') && ! $this->has('password_confirmation')) {
            $merge['password_confirmation'] = $this->input('confirmPassword');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
