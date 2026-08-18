<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends StoreShiftRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Shift $shift */
        $shift = $this->route('shift');
        $companyId = $this->user()?->company_id;

        return $this->baseRules() + [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:40',
                Rule::unique('shifts', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($shift->id),
            ],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
        ];
    }
}
