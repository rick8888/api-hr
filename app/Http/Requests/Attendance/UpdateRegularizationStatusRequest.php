<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Models\Regularization;
use App\Domain\Platform\Support\EntityStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegularizationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', EntityStatusCatalog::rule('regularizations', Regularization::STATUSES)],
        ];
    }
}
