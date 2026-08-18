<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PayrollSettingsService
{
    public function __construct(
        private readonly StatutoryComponentSyncService $statutorySync,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function getForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);

        return $this->format($company);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateForActor(User $actor, array $data): array
    {
        $company = $this->resolveCompany($actor);

        return DB::transaction(function () use ($company, $data) {
            $settings = is_array($company->settings) ? $company->settings : [];
            $payroll = is_array($settings['payroll'] ?? null) ? $settings['payroll'] : [];

            $previousMode = (string) ($payroll['statutory_mode']
                ?? StatutoryComponentSyncService::MODE_AUTOMATIC);

            $nextMode = (string) ($data['statutory_mode'] ?? $previousMode);
            if (! in_array($nextMode, [
                StatutoryComponentSyncService::MODE_AUTOMATIC,
                StatutoryComponentSyncService::MODE_MANUAL,
            ], true)) {
                $nextMode = StatutoryComponentSyncService::MODE_AUTOMATIC;
            }

            $payroll = array_merge($payroll, $this->normalizePayrollPayload($data));
            $payroll['statutory_mode'] = $nextMode;
            $settings['payroll'] = $payroll;

            $company->settings = $settings;
            $company->save();

            $sync = null;
            if ($nextMode !== $previousMode || $nextMode === StatutoryComponentSyncService::MODE_MANUAL) {
                // Re-sync on mode change; also refresh valid taxes when staying/switching to manual
                $sync = $this->statutorySync->applyMode($company, $nextMode);
            }

            $this->audit->log('payroll_settings.updated', $company, [
                'statutory_mode' => $nextMode,
                'sync' => $sync,
            ]);

            $formatted = $this->format($company->fresh());
            $formatted['sync'] = $sync;

            return $formatted;
        });
    }

    public static function statutoryModeForCompany(Company $company): string
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $payroll = is_array($settings['payroll'] ?? null) ? $settings['payroll'] : [];
        $mode = (string) ($payroll['statutory_mode'] ?? StatutoryComponentSyncService::MODE_AUTOMATIC);

        return $mode === StatutoryComponentSyncService::MODE_MANUAL
            ? StatutoryComponentSyncService::MODE_MANUAL
            : StatutoryComponentSyncService::MODE_AUTOMATIC;
    }

    private function resolveCompany(User $actor): Company
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin'], true)) {
            throw new AccessDeniedHttpException(
                'Accès non autorisé aux paramètres de paie. Compte tenant/admin avec entreprise requis.'
            );
        }

        return Company::query()->findOrFail($actor->company_id);
    }

    /** @return array<string, mixed> */
    private function format(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $payroll = is_array($settings['payroll'] ?? null) ? $settings['payroll'] : [];

        // Uniquement ce qui est stocké + métadonnées (pas de valeurs démo inventées).
        $payload = array_intersect_key($payroll, $this->defaults());
        $payload['company_id'] = $company->id;
        $payload['country_id'] = $company->country_id;

        if (array_key_exists('statutory_mode', $payroll)) {
            $payload['statutory_mode'] = self::statutoryModeForCompany($company);
        }

        return $payload;
    }

    /**
     * Clés autorisées pour le stockage (sans inventer de valeurs affichées).
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'statutory_mode' => null,
            'payroll_prefix' => null,
            'default_pay_period' => null,
            'payroll_start_day' => null,
            'payroll_end_day' => null,
            'pay_date_offset_days' => null,
            'working_days_per_month' => null,
            'lop_deduction_enabled' => null,
            'lop_calculation_method' => null,
            'lop_include_weekends' => null,
            'lop_include_holidays' => null,
            'lop_round_mode' => null,
            'expense_reimbursement_enabled' => null,
            'loan_deductions_enabled' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayrollPayload(array $data): array
    {
        $keys = array_keys($this->defaults());
        $out = [];

        $boolKeys = [
            'lop_deduction_enabled',
            'lop_include_weekends',
            'lop_include_holidays',
            'expense_reimbursement_enabled',
            'loan_deductions_enabled',
        ];
        $intKeys = [
            'payroll_start_day',
            'payroll_end_day',
            'pay_date_offset_days',
            'working_days_per_month',
        ];

        foreach ($keys as $key) {
            if ($key === 'statutory_mode') {
                continue;
            }
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (in_array($key, $boolKeys, true)) {
                $out[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
            } elseif (in_array($key, $intKeys, true)) {
                $out[$key] = (int) $value;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
