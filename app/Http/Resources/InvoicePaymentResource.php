<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Billing\Models\InvoicePayment */
class InvoicePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->invoice_number),
            'company_id' => $this->whenLoaded('invoice', fn () => $this->invoice->company_id),
            'company_name' => $this->whenLoaded('invoice', fn () => $this->invoice->company?->name),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'paid_at' => optional($this->paid_at)->toDateString(),
            'method' => $this->method,
            'reference' => $this->reference,
            'notes' => $this->notes,
        ];
    }
}
