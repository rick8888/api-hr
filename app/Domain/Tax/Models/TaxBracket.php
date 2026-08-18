<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_tax_id',
        'min_amount',
        'max_amount',
        'rate',
        'fixed_deduction',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'fixed_deduction' => 'decimal:2',
            'sequence' => 'integer',
        ];
    }

    public function legalTax(): BelongsTo
    {
        return $this->belongsTo(LegalTax::class);
    }
}
