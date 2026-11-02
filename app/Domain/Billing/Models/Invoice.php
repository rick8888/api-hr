<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'invoice_number',
        'plan_code',
        'description',
        'amount',
        'amount_paid',
        'currency',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_proof_path',
        'period_start',
        'period_end',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
