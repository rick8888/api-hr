<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use App\Application\DTO\Training\TrainingData;
use App\Domain\Platform\Models\TrainingCategory;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Domain\Training\Models\Training;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'category' => ['sometimes', 'required', 'string', 'max:80', TrainingCategory::codeRule()],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'objectives' => ['nullable', 'array'],
            'objectives.*' => ['string', 'max:255'],
            'status' => ['sometimes', 'string', EntityStatusCatalog::rule('trainings', Training::STATUSES)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'isActive' => 'is_active',
            'active' => 'is_active',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $this->merge([$to => $this->input($from)]);
            }
        }

        if ($this->has('category')) {
            $this->merge([
                'category' => TrainingData::normalizeCategory((string) $this->input('category')),
            ]);
        }
    }
}
