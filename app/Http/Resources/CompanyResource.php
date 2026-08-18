<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Company\Models\Company */
class CompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'name' => $this->name,
            'code' => $this->code,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'vat_number' => $settings['vat_number'] ?? $this->tax_id,
            'registration_number' => $this->registration_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'currency' => $this->currency,
            'is_active' => (bool) $this->is_active,
            'country' => $this->whenLoaded('country', fn () => $this->country->name),
            'country_code' => $this->whenLoaded('country', fn () => $this->country->code),
            'subdomain' => $settings['subdomain'] ?? strtolower((string) $this->code),
            'legal_form' => $settings['legal_form'] ?? null,
            'industry' => $settings['industry'] ?? null,
            'founded_at' => $settings['founded_at'] ?? null,
            'language' => $settings['language'] ?? 'fr',
            'timezone' => $settings['timezone'] ?? null,
            'headcount_range' => $settings['headcount_range'] ?? null,
            'plan' => $settings['plan'] ?? 'Starter',
            'website' => $settings['website'] ?? null,
            'address' => $settings['address'] ?? [
                'street' => '',
                'city' => '',
                'postal_code' => '',
                'state' => '',
            ],
            'primary_contact' => $settings['primary_contact'] ?? null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
