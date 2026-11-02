<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class SuspendEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $map = [
            'suspensionDate' => 'suspension_date',
            'notifyByEmail' => 'notify_by_email',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $merge[$to] = $this->input($from);
            }
        }

        if ($this->exists('duration') && $this->input('duration') === '') {
            $merge['duration'] = null;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'suspension_date' => ['required', 'date'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'reason' => ['required', 'string', 'max:2000'],
            'notify_by_email' => ['sometimes', 'boolean'],
        ];
    }
}
