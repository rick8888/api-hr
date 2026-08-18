<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingEnrollmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('training_enrollments', TrainingEnrollment::STATUSES)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score' => ['nullable', 'string', 'max:40'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
