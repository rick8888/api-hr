<?php

declare(strict_types=1);

namespace App\Http\Requests\Recruitment;

use App\Application\DTO\Recruitment\JobOpeningData;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Domain\Recruitment\Models\JobOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'designation_id' => ['sometimes', 'required', 'integer'],
            'title' => ['nullable', 'string', 'max:180'],
            'department' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:180'],
            'type' => ['sometimes', 'required', 'string', Rule::in(JobOpening::TYPES)],
            'status' => ['sometimes', 'required', 'string', EntityStatusCatalog::rule('job_openings', JobOpening::STATUSES)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('designationId') && ! $this->has('designation_id')) {
            $this->merge(['designation_id' => $this->input('designationId')]);
        }
        if ($this->has('type')) {
            $this->merge(['type' => JobOpeningData::normalizeType((string) $this->input('type'))]);
        }
        if ($this->has('status')) {
            $this->merge(['status' => JobOpeningData::normalizeStatus((string) $this->input('status'))]);
        }
    }
}
