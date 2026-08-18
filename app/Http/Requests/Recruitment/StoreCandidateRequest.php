<?php

declare(strict_types=1);

namespace App\Http\Requests\Recruitment;

use App\Application\DTO\Recruitment\CandidateData;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Domain\Recruitment\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180'],
            'job_opening_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'string', 'max:180'],
            'experience' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:80'],
            'availability' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'stage' => ['nullable', 'string', EntityStatusCatalog::rule('candidates', Candidate::STAGES)],
            'last_action' => ['nullable', 'string', 'max:180'],
            'applied_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'jobOpeningId' => 'job_opening_id',
            'lastAction' => 'last_action',
            'appliedDate' => 'applied_date',
        ];

        foreach ($map as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $this->merge([$to => $this->input($from)]);
            }
        }

        if ($this->has('status') && ! $this->has('stage')) {
            $this->merge(['stage' => $this->input('status')]);
        }

        if ($this->has('stage')) {
            $this->merge([
                'stage' => CandidateData::normalizeStage((string) $this->input('stage')),
            ]);
        }
    }
}
