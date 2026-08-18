<?php

declare(strict_types=1);

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class ReportEquipmentIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('issue_note') && ! $this->has('note')) {
            $this->merge(['note' => $this->input('issue_note')]);
        }
        if ($this->has('issueNote') && ! $this->has('note')) {
            $this->merge(['note' => $this->input('issueNote')]);
        }
    }
}
