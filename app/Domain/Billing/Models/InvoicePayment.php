<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    public const METHOD_BANK_TRANSFER = PaymentMethod::CODE_BANK_TRANSFER;

    public const METHOD_MOBILE_MONEY = PaymentMethod::CODE_MOBILE_MONEY;

    public const METHOD_CASH = PaymentMethod::CODE_CASH;

    protected $fillable = [
        'invoice_id',
        'amount',
        'currency',
        'paid_at',
        'method',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
