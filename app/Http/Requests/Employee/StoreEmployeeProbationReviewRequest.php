<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Domain\Employee\Models\EmployeeProbationReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeProbationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $decision = $this->input('decision');
        if (is_string($decision)) {
            $this->merge(['decision' => strtolower(trim($decision))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'review_date' => ['required', 'date'],
            'decision' => [
                'required',
                'string',
                Rule::in([
                    EmployeeProbationReview::DECISION_CHECK_IN,
                    EmployeeProbationReview::DECISION_CONFIRMED,
                    EmployeeProbationReview::DECISION_EXTENDED,
                    EmployeeProbationReview::DECISION_TERMINATED,
                ]),
            ],
            'extended_until' => [
                Rule::requiredIf($this->input('decision') === EmployeeProbationReview::DECISION_EXTENDED),
                'nullable',
                'date',
                'after:today',
            ],
            'notes' => ['nullable', 'string', 'max:10000'],
            'termination.type' => [
                Rule::requiredIf($this->input('decision') === EmployeeProbationReview::DECISION_TERMINATED),
                'nullable',
                'string',
                Rule::exists('termination_types', 'code')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'termination.exit_date' => [
                Rule::requiredIf($this->input('decision') === EmployeeProbationReview::DECISION_TERMINATED),
                'nullable',
                'date',
            ],
            'termination.last_working_day' => [
                Rule::requiredIf($this->input('decision') === EmployeeProbationReview::DECISION_TERMINATED),
                'nullable',
                'date',
                'before_or_equal:termination.exit_date',
            ],
            'termination.reason' => [
                Rule::requiredIf($this->input('decision') === EmployeeProbationReview::DECISION_TERMINATED),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
