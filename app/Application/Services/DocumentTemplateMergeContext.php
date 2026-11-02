<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Platform\Models\TerminationType;
use Carbon\Carbon;

final class DocumentTemplateMergeContext
{
    /** @param array<string, mixed> $extra */
    public function build(Employee $employee, ?Company $company = null, array $extra = []): array
    {
        $employee->loadMissing('company', 'country');
        $company ??= $employee->company;

        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $termination = is_array($metadata['termination'] ?? null) ? $metadata['termination'] : [];
        $suspension = is_array($metadata['suspension'] ?? null) ? $metadata['suspension'] : [];

        $terminationTypeLabel = null;
        $terminationCode = $termination['type'] ?? null;
        if (is_string($terminationCode) && $terminationCode !== '') {
            $terminationTypeLabel = TerminationType::query()
                ->where('code', $terminationCode)
                ->value('name') ?? $terminationCode;
        }

        $today = Carbon::now();

        $tree = [
            'employee' => [
                'full_name' => $employee->full_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'matricule' => $employee->matricule,
                'job_title' => $employee->job_title,
                'department' => $employee->department,
                'contract_type' => $employee->contract_type,
                'hired_at' => $this->formatDate($employee->hired_at),
                'hired_at_long' => $this->formatDateLong($employee->hired_at),
                'terminated_at' => $this->formatDate($employee->terminated_at),
                'national_id' => $employee->national_id,
                'base_salary' => $employee->base_salary !== null ? number_format((float) $employee->base_salary, 2, ',', ' ') : '',
                'currency' => $employee->currency,
            ],
            'company' => [
                'name' => $company?->name ?? '',
                'legal_name' => $company?->legal_name ?? $company?->name ?? '',
                'email' => $company?->email ?? '',
                'phone' => $company?->phone ?? '',
                'address' => $company?->address ?? '',
                'tax_id' => $company?->tax_id ?? '',
                'registration_number' => $company?->registration_number ?? '',
            ],
            'termination' => [
                'type' => $terminationCode ?? '',
                'type_label' => $terminationTypeLabel ?? '',
                'date' => $this->formatDate(
                    $termination['exit_date'] ?? $termination['date'] ?? $employee->terminated_at,
                ),
                'exit_date' => $this->formatDate($termination['exit_date'] ?? $employee->terminated_at),
                'last_working_day' => $this->formatDate($termination['last_working_day'] ?? null),
                'reason' => (string) ($termination['reason'] ?? ''),
                'notice_period' => (string) ($termination['notice_period'] ?? ''),
            ],
            'suspension' => [
                'date' => $this->formatDate($suspension['date'] ?? null),
                'duration_days' => filled($suspension['duration_days'] ?? null)
                    ? (string) $suspension['duration_days']
                    : '',
                'reason' => (string) ($suspension['reason'] ?? ''),
                'ends_at' => $this->formatDate($suspension['ends_at'] ?? null),
            ],
            'today' => $today->format('d/m/Y'),
            'today_long' => $this->formatDateLong($today),
            'extra' => $extra,
        ];

        return [
            'tree' => $tree,
            'flat' => $this->flatten($tree),
        ];
    }

    /** @return array{tree: array<string, mixed>, flat: array<string, string>} */
    public function buildSample(): array
    {
        $catalog = $this->variableCatalog();
        $flat = [];
        foreach ($catalog as $row) {
            $flat[$row['key']] = (string) ($row['example'] ?? '');
        }

        return [
            'tree' => ['sample' => true],
            'flat' => $flat,
        ];
    }

    /** @return list<array{key: string, label: string, example: string}> */
    public function variableCatalog(): array
    {
        return [
            ['key' => 'employee.full_name', 'label' => 'Nom complet', 'example' => 'Jean Dupont'],
            ['key' => 'employee.first_name', 'label' => 'Prénom', 'example' => 'Jean'],
            ['key' => 'employee.last_name', 'label' => 'Nom', 'example' => 'Dupont'],
            ['key' => 'employee.email', 'label' => 'E-mail', 'example' => 'jean@example.com'],
            ['key' => 'employee.matricule', 'label' => 'Matricule', 'example' => 'EMP-001'],
            ['key' => 'employee.job_title', 'label' => 'Poste', 'example' => 'Comptable'],
            ['key' => 'employee.department', 'label' => 'Département', 'example' => 'Finance'],
            ['key' => 'employee.contract_type', 'label' => 'Type de contrat', 'example' => 'CDI'],
            ['key' => 'employee.hired_at', 'label' => 'Date d’embauche', 'example' => '01/03/2024'],
            ['key' => 'employee.hired_at_long', 'label' => 'Date d’embauche (long)', 'example' => '1 mars 2024'],
            ['key' => 'employee.terminated_at', 'label' => 'Date de fin', 'example' => '15/06/2025'],
            ['key' => 'employee.base_salary', 'label' => 'Salaire de base', 'example' => '3 500,00'],
            ['key' => 'employee.currency', 'label' => 'Devise', 'example' => 'MAD'],
            ['key' => 'company.name', 'label' => 'Société', 'example' => 'Bravo SARL'],
            ['key' => 'company.legal_name', 'label' => 'Raison sociale', 'example' => 'Bravo SARL'],
            ['key' => 'company.address', 'label' => 'Adresse société', 'example' => '10 rue Example'],
            ['key' => 'company.email', 'label' => 'E-mail société', 'example' => 'rh@bravo.com'],
            ['key' => 'company.phone', 'label' => 'Téléphone société', 'example' => '+212…'],
            ['key' => 'company.tax_id', 'label' => 'Identifiant fiscal', 'example' => '…'],
            ['key' => 'termination.type_label', 'label' => 'Type de fin de contrat', 'example' => 'Licenciement'],
            ['key' => 'termination.date', 'label' => 'Date de fin de contrat', 'example' => '30/09/2025'],
            ['key' => 'termination.exit_date', 'label' => 'Date de sortie', 'example' => '30/09/2025'],
            ['key' => 'termination.last_working_day', 'label' => 'Dernier jour travaillé', 'example' => '28/09/2025'],
            ['key' => 'termination.reason', 'label' => 'Motif de fin', 'example' => '…'],
            ['key' => 'suspension.date', 'label' => 'Date de suspension', 'example' => '01/08/2025'],
            ['key' => 'suspension.duration_days', 'label' => 'Durée suspension (jours)', 'example' => '7'],
            ['key' => 'suspension.reason', 'label' => 'Motif suspension', 'example' => '…'],
            ['key' => 'today', 'label' => 'Date du jour', 'example' => '18/09/2025'],
            ['key' => 'today_long', 'label' => 'Date du jour (long)', 'example' => '18 septembre 2025'],
        ];
    }

    /** @param array<string, mixed> $tree */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $flat = [];
        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $path));
            } elseif ($value === null || is_scalar($value)) {
                $flat[$path] = (string) $value;
            } else {
                $flat[$path] = '';
            }
        }

        return $flat;
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatDateLong(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->locale('fr')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
