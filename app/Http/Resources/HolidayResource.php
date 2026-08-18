<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Organization\Models\Holiday */
class HolidayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'date' => optional($this->date)->toDateString(),
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'color' => $this->color,
            'applicable_to' => $this->applicable_to,
            'recurring' => (bool) $this->recurring,
            'optional_holiday' => (bool) $this->optional_holiday,
            'restricted_holiday' => (bool) $this->restricted_holiday,
            'half_day' => (bool) $this->half_day,
            'visible_to_employees' => (bool) $this->visible_to_employees,
            'send_notification' => (bool) $this->send_notification,
            'active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
