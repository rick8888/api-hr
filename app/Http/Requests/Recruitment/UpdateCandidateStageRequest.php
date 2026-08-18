<?php

declare(strict_types=1);

namespace App\Http\Requests\Recruitment;

use App\Application\DTO\Recruitment\CandidateData;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Domain\Recruitment\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', EntityStatusCatalog::rule('candidates', Candidate::STAGES)],
        ];
    }

    protected function prepareForValidation(): void
    {
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
