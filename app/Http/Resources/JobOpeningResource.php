<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTO\Recruitment\JobOpeningData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Recruitment\Models\JobOpening */
class JobOpeningResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $designation = $this->relationLoaded('designation') ? $this->designation : null;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'designation_id' => $this->designation_id,
            'designationId' => $this->designation_id,
            'designation_name' => $designation?->name ?? $this->title,
            'title' => $designation?->name ?? $this->title,
            'department' => $this->department
                ?? $designation?->department?->name,
            'location' => $this->location,
            'type' => JobOpeningData::typeLabel((string) $this->type),
            'type_key' => $this->type,
            'status' => JobOpeningData::statusLabel((string) $this->status),
            'status_key' => $this->status,
            'description' => $this->description,
            'applications' => (int) ($this->applications_count ?? 0),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
