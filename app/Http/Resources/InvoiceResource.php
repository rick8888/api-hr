<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Billing\Models\Invoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company->name),
            'plan_code' => $this->plan_code,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'amount_paid' => (float) $this->amount_paid,
            'currency' => $this->currency,
            'status' => $this->status,
            'issue_date' => optional($this->issue_date)->toDateString(),
            'due_date' => optional($this->due_date)->toDateString(),
            'paid_at' => optional($this->paid_at)->toDateString(),
            'payment_method' => $this->payment_method,
            'has_payment_proof' => (bool) $this->payment_proof_path,
            'payment_proof_url' => $this->payment_proof_path
                ? route('invoices.payment-proof', ['invoice' => $this->id])
                : null,
            'period_start' => optional($this->period_start)->toDateString(),
            'period_end' => optional($this->period_end)->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
