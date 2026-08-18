<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Attendance\Models\Shift */
class ShiftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $start = $this->formatTime($this->start_time);
        $end = $this->formatTime($this->end_time);

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'start_time' => $start,
            'end_time' => $end,
            'start' => $start,
            'end' => $end,
            'working_days' => $this->working_days ?? [],
            'days' => $this->working_days ?? [],
            'note' => $this->note,
            'active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function formatTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '09:00';
        }

        return substr((string) $time, 0, 5);
    }
}
