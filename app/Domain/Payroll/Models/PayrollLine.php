<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayrollLine extends Model
{
    use HasFactory;

    public const KIND_COMPONENT = 'component';
    public const KIND_TAX_EMPLOYER = 'tax_employer';
    public const KIND_TAX_EMPLOYEE = 'tax_employee';
    public const KIND_MODIFIER = 'modifier';
    public const KIND_SUMMARY = 'summary';

    public const TYPE_EARNING = 'earning';
    public const TYPE_DEDUCTION = 'deduction';
    public const TYPE_INFO = 'info';

    protected $fillable = [
        'payroll_id',
        'kind',
        'source_type',
        'source_id',
        'code',
        'label',
        'type',
        'base_amount',
        'rate',
        'amount',
        'is_taxable',
        'affects_gross',
        'affects_net',
        'sequence',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
            'affects_gross' => 'boolean',
            'affects_net' => 'boolean',
            'sequence' => 'integer',
            'meta' => 'array',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
