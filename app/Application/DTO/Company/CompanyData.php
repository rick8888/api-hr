<?php

declare(strict_types=1);

namespace App\Application\DTO\Company;

final class CompanyData
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public readonly int $countryId,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $legalName = null,
        public readonly ?string $taxId = null,
        public readonly ?string $registrationNumber = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $currency = null,
        public readonly bool $isActive = true,
        public readonly array $settings = [],
        public readonly ?string $contactPassword = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return [
            'country_id' => $this->countryId,
            'name' => $this->name,
            'code' => strtoupper($this->code),
            'legal_name' => $this->legalName ?? $this->name,
            'tax_id' => $this->taxId,
            'registration_number' => $this->registrationNumber,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'is_active' => $this->isActive,
            'settings' => $this->settings,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $contactPassword = self::extractContactPassword($data);
        $settings = self::buildSettings($data);

        return new self(
            countryId: (int) $data['country_id'],
            name: (string) $data['name'],
            code: (string) $data['code'],
            legalName: $data['legal_name'] ?? $data['name'] ?? null,
            taxId: $data['tax_id'] ?? $data['vat_number'] ?? null,
            registrationNumber: $data['registration_number'] ?? null,
            email: $data['email'] ?? ($data['primary_contact']['email'] ?? null),
            phone: $data['phone'] ?? ($data['primary_contact']['phone'] ?? null),
            address: self::formatAddressLine($data['address'] ?? null),
            currency: $data['currency'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            settings: $settings,
            contactPassword: $contactPassword,
        );
    }

    /** @param array<string, mixed> $data */
    private static function extractContactPassword(array $data): ?string
    {
        $password = $data['primary_contact']['password'] ?? null;

        if (! is_string($password)) {
            return null;
        }

        $password = trim($password);

        return $password !== '' ? $password : null;
    }

    /** @param array<string, mixed> $data */
    private static function buildSettings(array $data): array
    {
        $keys = [
            'subdomain',
            'legal_form',
            'vat_number',
            'industry',
            'founded_at',
            'language',
            'timezone',
            'headcount_range',
            'plan',
            'website',
        ];

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $settings[$key] = $data[$key];
            }
        }

        if (isset($data['address']) && is_array($data['address'])) {
            $settings['address'] = $data['address'];
        }

        if (isset($data['primary_contact']) && is_array($data['primary_contact'])) {
            $contact = $data['primary_contact'];
            $contact['role'] = $contact['role'] ?? 'tenant';
            unset($contact['password']);
            $settings['primary_contact'] = $contact;
        }

        if (! empty($data['tax_id']) || ! empty($data['vat_number'])) {
            $settings['vat_number'] = $data['vat_number'] ?? $data['tax_id'];
        }

        return $settings;
    }

    /** @param array<string, mixed>|string|null $address */
    private static function formatAddressLine(array|string|null $address): ?string
    {
        if (is_string($address)) {
            return $address !== '' ? $address : null;
        }

        if (! is_array($address)) {
            return null;
        }

        $parts = array_filter([
            $address['street'] ?? null,
            trim(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? '')),
            $address['state'] ?? null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }
}
