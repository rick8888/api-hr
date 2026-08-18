<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    public const CODE_BANK_TRANSFER = 'bank_transfer';

    public const CODE_MOBILE_MONEY = 'mobile_money';

    public const CODE_CASH = 'cash';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'label',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
