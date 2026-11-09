<?php

declare(strict_types=1);

namespace App\Http\Requests\Performance;

use App\Domain\Employee\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period_start' => ['sometimes', 'required', 'date'],
            'period_end' => ['sometimes', 'required', 'date'],
            'review_date' => ['sometimes', 'required', 'date'],
            'rating' => ['sometimes', 'required', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:10000'],
            'improvement_areas' => ['nullable', 'string', 'max:10000'],
            'comments' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['sometimes', 'nullable', 'array', 'max:10'],
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

                $review = $this->route('performance_review');
                if (! $review instanceof PerformanceReview) {
                    return;
                }

                $periodStart = $this->input('period_start', $review->period_start?->toDateString());
                $periodEnd = $this->input('period_end', $review->period_end?->toDateString());
                $reviewDate = $this->input('review_date', $review->review_date?->toDateString());

                if (! $periodStart || ! $periodEnd || ! $reviewDate) {
                    return;
                }

                if (Carbon::parse($periodEnd)->lt(Carbon::parse($periodStart))) {
                    $validator->errors()->add('period_end', 'La fin de période doit être postérieure au début.');
                }
                if (
                    Carbon::parse($reviewDate)->lt(Carbon::parse($periodStart))
                    || Carbon::parse($reviewDate)->gt(Carbon::parse($periodEnd))
                ) {
                    $validator->errors()->add('review_date', 'La date d’évaluation doit être comprise dans la période évaluée.');
                }
            },
        ];
    }
}
