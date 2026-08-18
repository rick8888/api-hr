<?php

declare(strict_types=1);

namespace App\Application\DTO\Employee;

final class EmployeeData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $countryId,
        public readonly string $matricule,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $jobTitle = null,
        public readonly ?string $department = null,
        public readonly ?string $contractType = 'CDI',
        public readonly float $baseSalary = 0,
        public readonly ?string $currency = null,
        public readonly ?string $hiredAt = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $nationalId = null,
        public readonly ?string $socialSecurityNumber = null,
        public readonly ?string $taxNumber = null,
        public readonly ?array $metadata = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toModelArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'country_id' => $this->countryId,
            'matricule' => $this->matricule,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'department' => $this->department,
            'contract_type' => $this->contractType,
            'base_salary' => $this->baseSalary,
            'currency' => $this->currency,
            'hired_at' => $this->hiredAt,
            'date_of_birth' => $this->dateOfBirth,
            'national_id' => $this->nationalId,
            'social_security_number' => $this->socialSecurityNumber,
            'tax_number' => $this->taxNumber,
            'metadata' => $this->metadata,
            'is_active' => $this->isActive,
        ], fn ($v) => $v !== null);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $metadata = $data['metadata'] ?? null;
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : null;
        }

        return new self(
            companyId: (int) $data['company_id'],
            countryId: (int) $data['country_id'],
            matricule: (string) $data['matricule'],
            firstName: (string) $data['first_name'],
            lastName: (string) $data['last_name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            jobTitle: $data['job_title'] ?? null,
            department: $data['department'] ?? null,
            contractType: $data['contract_type'] ?? 'CDI',
            baseSalary: (float) ($data['base_salary'] ?? 0),
            currency: $data['currency'] ?? null,
            hiredAt: $data['hired_at'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            nationalId: $data['national_id'] ?? null,
            socialSecurityNumber: $data['social_security_number'] ?? null,
            taxNumber: $data['tax_number'] ?? null,
            metadata: is_array($metadata) ? $metadata : null,
            isActive: (bool) ($data['is_active'] ?? $data['active'] ?? true),
        );
    }
}
