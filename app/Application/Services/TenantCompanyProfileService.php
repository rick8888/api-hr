<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Company\CompanyData;
use App\Domain\Company\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

final class TenantCompanyProfileService
{
    public function __construct(private readonly CompanyService $companyService)
    {
    }

    /** @return array<string, mixed> */
    public function getForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);
        $company->load('country');

        return $this->formatProfile($company);
    }

    /** @param array<string, mixed> $data */
    public function updateForActor(User $actor, array $data): array
    {
        $company = $this->resolveCompany($actor);
        $updated = $this->companyService->update(
            $company,
            CompanyData::fromArray($this->buildUpdatePayload($company, $data))
        );
        $updated->load('country');

        return $this->formatProfile($updated);
    }

    private function resolveCompany(User $actor): Company
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin'], true)) {
            throw new AuthorizationException('Accès non autorisé au profil entreprise.');
        }

        return Company::query()->findOrFail($actor->company_id);
    }

    /** @return array<string, mixed> */
    private function formatProfile(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $address = is_array($settings['address'] ?? null) ? $settings['address'] : [];
        $subdomain = (string) ($settings['subdomain'] ?? strtolower((string) $company->code));

        return [
            'company_name' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $settings['website'] ?? null,
            'tax_id' => $settings['vat_number'] ?? $company->tax_id,
            'address' => [
                'street' => $address['street'] ?? '',
                'city' => $address['city'] ?? '',
                'state' => $address['state'] ?? '',
                'postal_code' => $address['postal_code'] ?? '',
            ],
            'country' => $company->country?->name,
            'account_status' => $company->is_active ? 'Active' : 'Suspended',
            'member_since' => $this->formatDate($company->created_at),
            'subdomain' => $subdomain.'.opencorebs.com',
        ];
    }

    /** @param array<string, mixed> $data */
    private function buildUpdatePayload(Company $company, array $data): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $address = is_array($settings['address'] ?? null) ? $settings['address'] : [];

        if (isset($data['address']) && is_array($data['address'])) {
            $address = array_merge($address, $data['address']);
            $settings['address'] = $address;
        }

        if (array_key_exists('website', $data)) {
            $settings['website'] = $data['website'];
        }

        if (array_key_exists('tax_id', $data)) {
            $settings['vat_number'] = $data['tax_id'];
        }

        return [
            'country_id' => $company->country_id,
            'name' => $data['name'] ?? $company->name,
            'code' => $company->code,
            'legal_name' => $company->legal_name,
            'tax_id' => $data['tax_id'] ?? $company->tax_id,
            'registration_number' => $company->registration_number,
            'email' => $company->email,
            'phone' => $data['phone'] ?? $company->phone,
            'currency' => $company->currency,
            'is_active' => $company->is_active,
            'subdomain' => $settings['subdomain'] ?? strtolower((string) $company->code),
            'legal_form' => $settings['legal_form'] ?? null,
            'industry' => $settings['industry'] ?? null,
            'founded_at' => $settings['founded_at'] ?? null,
            'language' => $settings['language'] ?? 'fr',
            'timezone' => $settings['timezone'] ?? null,
            'headcount_range' => $settings['headcount_range'] ?? null,
            'plan' => $settings['plan'] ?? null,
            'website' => $settings['website'] ?? null,
            'address' => $address,
            'primary_contact' => $settings['primary_contact'] ?? null,
        ];
    }

    private function formatDate(mixed $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Carbon::parse($date)->format('M j, Y');
    }
}
