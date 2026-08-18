<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Domain\Payroll\Models\SalaryComponent;
use App\Domain\Tax\Models\LegalTax;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeds / hides country legal taxes as company salary components
 * when switching payroll statutory mode between automatic and manual.
 */
final class StatutoryComponentSyncService
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    /**
     * @return array{mode: string, created: int, restored: int, hidden: int, total_visible: int}
     */
    public function applyMode(Company $company, string $mode): array
    {
        $mode = $mode === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_AUTOMATIC;

        if ($mode === self::MODE_MANUAL) {
            $result = $this->revealForManualMode($company);
        } else {
            $result = $this->hideForAutomaticMode($company);
        }

        return [
            'mode' => $mode,
            'created' => $result['created'],
            'restored' => $result['restored'],
            'hidden' => $result['hidden'],
            'total_visible' => $this->countVisibleStatutory($company),
        ];
    }

    /**
     * Insert / restore currently valid country cotisations as active components.
     *
     * @return array{created: int, restored: int, hidden: int}
     */
    public function revealForManualMode(Company $company): array
    {
        $taxes = $this->validLegalTaxesForCountry((int) $company->country_id);
        $created = 0;
        $restored = 0;

        foreach ($taxes as $tax) {
            $existing = SalaryComponent::withTrashed()
                ->where('company_id', $company->id)
                ->where(function ($q) use ($tax) {
                    $q->where('legal_tax_id', $tax->id)
                        ->orWhere(function ($q2) use ($tax) {
                            $q2->where('is_statutory', true)
                                ->where('code', $tax->code);
                        });
                })
                ->first();

            $payload = $this->mapTaxToComponent($company, $tax);

            if ($existing === null) {
                SalaryComponent::query()->create($payload);
                $created++;
                continue;
            }

            if ($existing->trashed()) {
                $existing->restore();
                $restored++;
            }

            $existing->fill($payload)->save();
            if (! $existing->is_active) {
                $existing->is_active = true;
                $existing->save();
                $restored++;
            }
        }

        // Hide statutory components whose legal tax is no longer valid
        $validIds = $taxes->pluck('id')->all();
        $hidden = SalaryComponent::query()
            ->where('company_id', $company->id)
            ->where('is_statutory', true)
            ->where('is_active', true)
            ->where(function ($q) use ($validIds) {
                $q->whereNotIn('legal_tax_id', $validIds)
                    ->orWhereNull('legal_tax_id');
            })
            ->update(['is_active' => false]);

        return [
            'created' => $created,
            'restored' => $restored,
            'hidden' => (int) $hidden,
        ];
    }

    /**
     * Hide statutory cotisation components (catalog cleans up; TaxEngine applies them).
     *
     * @return array{created: int, restored: int, hidden: int}
     */
    public function hideForAutomaticMode(Company $company): array
    {
        $hidden = SalaryComponent::query()
            ->where('company_id', $company->id)
            ->where('is_statutory', true)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return [
            'created' => 0,
            'restored' => 0,
            'hidden' => (int) $hidden,
        ];
    }

    /** @return Collection<int, LegalTax> */
    public function validLegalTaxesForCountry(int $countryId, ?Carbon $on = null): Collection
    {
        $on = $on ?? Carbon::today();

        return LegalTax::query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($on) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $on);
            })
            ->where(function ($q) use ($on) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on);
            })
            ->orderBy('sequence')
            ->get();
    }

    private function countVisibleStatutory(Company $company): int
    {
        return SalaryComponent::query()
            ->where('company_id', $company->id)
            ->where('is_statutory', true)
            ->where('is_active', true)
            ->count();
    }

    /** @return array<string, mixed> */
    private function mapTaxToComponent(Company $company, LegalTax $tax): array
    {
        [$calculationType, $defaultValue, $formula] = $this->mapCalculation($tax);

        return [
            'company_id' => $company->id,
            'country_id' => $company->country_id,
            'legal_tax_id' => $tax->id,
            'code' => $tax->code,
            'name' => $tax->name,
            'description' => $tax->description,
            'type' => SalaryComponent::TYPE_DEDUCTION,
            'calculation_type' => $calculationType,
            'default_value' => $defaultValue,
            'formula' => $formula,
            'base_target' => $tax->base_target ?: LegalTax::BASE_GROSS,
            'is_taxable' => false,
            'affects_gross' => false,
            'affects_net' => true,
            'is_active' => true,
            'is_statutory' => true,
            'sequence' => 500 + (int) $tax->sequence,
            'depends_on' => null,
        ];
    }

    /**
     * @return array{0: string, 1: float, 2: ?string}
     */
    private function mapCalculation(LegalTax $tax): array
    {
        return match ($tax->type) {
            LegalTax::TYPE_PERCENT => [
                SalaryComponent::CALC_PERCENT,
                (float) $tax->employee_rate,
                null,
            ],
            LegalTax::TYPE_FIXED => [
                SalaryComponent::CALC_FIXED,
                (float) $tax->employee_rate,
                null,
            ],
            LegalTax::TYPE_FORMULA => [
                SalaryComponent::CALC_FORMULA,
                0.0,
                $tax->formula,
            ],
            default => [
                // progressive / unknown → resolve via legal tax at payroll time
                SalaryComponent::CALC_TAX,
                0.0,
                null,
            ],
        };
    }
}
