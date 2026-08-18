<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Company\Models\Company;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CompanySubscriptionSeeder extends Seeder
{
    /** @var array<string, string> */
    private const PLAN_ALIASES = [
        'enterprise' => 'enterprise',
        'entreprise' => 'enterprise',
        'business' => 'premium',
        'premium' => 'premium',
        'starter' => 'starter',
        'free' => 'free',
    ];

    public function run(): void
    {
        Company::query()->each(function (Company $company): void {
            $plan = $this->resolvePlan($company);

            if ($plan === null) {
                return;
            }

            $invoice = Invoice::query()
                ->where('company_id', $company->id)
                ->where('status', Invoice::STATUS_PAID)
                ->orderByDesc('period_end')
                ->first();

            $startsAt = $invoice?->period_start ?? $company->created_at?->toDateString() ?? now()->toDateString();
            $endsAt = $invoice?->period_end ?? Carbon::parse($startsAt)->addMonth()->toDateString();

            Subscription::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'status' => Subscription::STATUS_ACTIVE,
                ],
                [
                    'subscription_plan_id' => $plan->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'cancelled_at' => null,
                ]
            );
        });
    }

    private function resolvePlan(Company $company): ?SubscriptionPlan
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $planKey = trim((string) ($settings['plan'] ?? ''));

        if ($planKey === '') {
            return SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();
        }

        $normalized = strtolower($planKey);
        $code = self::PLAN_ALIASES[$normalized] ?? $normalized;

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->where(function ($query) use ($planKey, $normalized, $code) {
                $query->where('code', $code)
                    ->orWhere('code', $normalized)
                    ->orWhere('name', $planKey)
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first();
    }
}
