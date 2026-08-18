<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTO\Recruitment\CandidateData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Recruitment\Models\Candidate */
class CandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stageLabel = CandidateData::stageLabel((string) $this->stage);

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'job_opening_id' => $this->job_opening_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position
                ?? $this->jobOpening?->designation?->name
                ?? $this->jobOpening?->title,
            'experience' => $this->experience,
            'source' => $this->source,
            'availability' => $this->availability,
            'summary' => $this->summary,
            'stage' => $stageLabel,
            'status' => $stageLabel,
            'stage_key' => $this->stage,
            'last_action' => $this->last_action,
            'lastAction' => $this->last_action,
            'applied_date' => optional($this->applied_date)->toDateString(),
            'appliedDate' => optional($this->applied_date)->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
