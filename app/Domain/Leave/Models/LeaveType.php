<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use SoftDeletes;

    public const ACCRUAL_FREQUENCIES = ['monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'proof_required',
        'is_compensatory_off_type',
        'enable_automatic_accrual',
        'accrual_frequency',
        'accrual_rate',
        'max_accrual_limit',
        'allow_carry_forward',
        'max_carry_forward_days',
        'carry_forward_expiry_months',
        'allow_encashment',
        'max_encashment_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'proof_required' => 'boolean',
            'is_compensatory_off_type' => 'boolean',
            'enable_automatic_accrual' => 'boolean',
            'accrual_rate' => 'float',
            'max_accrual_limit' => 'integer',
            'allow_carry_forward' => 'boolean',
            'max_carry_forward_days' => 'integer',
            'carry_forward_expiry_months' => 'integer',
            'allow_encashment' => 'boolean',
            'max_encashment_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
