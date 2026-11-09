<?php

declare(strict_types=1);

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'review_date' => ['required', 'date'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:10000'],
            'improvement_areas' => ['nullable', 'string', 'max:10000'],
            'comments' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'array', 'max:10'],
            'objectives.*.title' => ['required', 'string', 'max:180'],
            'objectives.*.description' => ['nullable', 'string', 'max:5000'],
            'objectives.*.due_date' => ['nullable', 'date'],
            'objectives.*.status' => ['nullable', 'in:pending,in_progress,completed'],
            'objectives.*.progress' => ['nullable', 'integer', 'between:0,100'],
            'objectives.*.training_id' => [
                'nullable',
                'integer',
                Rule::exists('trainings', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->user()?->company_id)
                    ->where('status', 'published')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $validator->errors()->has('period_start')
                    || $validator->errors()->has('period_end')
                    || $validator->errors()->has('review_date')
                ) {
                    return;
                }

                if (
                    $this->filled('period_start')
                    && $this->filled('period_end')
                    && $this->date('review_date')
                    && (
                        $this->date('review_date')->lt($this->date('period_start'))
                        || $this->date('review_date')->gt($this->date('period_end'))
                    )
                ) {
                    $validator->errors()->add('review_date', 'La date d’évaluation doit être comprise dans la période évaluée.');
                }
            },
        ];
    }
}
