<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerminateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $map = [
            'rehireEligible' => 'rehire_eligible',
            'exitDate' => 'exit_date',
            'lastWorkingDay' => 'last_working_day',
            'notifyByEmail' => 'notify_by_email',
            'generateTerminationLetter' => 'generate_termination_letter',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $merge[$to] = $this->input($from);
            }
        }

        if ($this->has('type') || isset($merge['type'])) {
            $raw = (string) ($merge['type'] ?? $this->input('type'));
            $merge['type'] = $this->normalizeType($raw);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                'max:80',
                Rule::exists('termination_types', 'code')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'rehire_eligible' => ['sometimes', 'boolean'],
            'exit_date' => ['required', 'date'],
            'last_working_day' => ['required', 'date', 'lte:exit_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'notify_by_email' => ['sometimes', 'boolean'],
            'generate_termination_letter' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim(str_replace(['-', ' '], '_', $type)));

        $aliases = [
            'contractend' => 'contract_end',
            'contract_end' => 'contract_end',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}
