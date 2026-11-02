<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class TenantPlanQuotaService
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

    public function assertCanAddResources(int $companyId, bool $user = false, int $employees = 0): void
    {
        $company = Company::query()
            ->whereKey($companyId)
            ->lockForUpdate()
            ->firstOrFail();
        $plan = $this->planForCompany($company);

        if ($user && $plan?->max_users !== null) {
            $used = $this->usersUsed($companyId);
            if ($used >= $plan->max_users) {
                throw ValidationException::withMessages([
                    'quota.users' => [
                        "La limite de {$plan->max_users} utilisateurs de votre forfait est atteinte. Changez de forfait pour en ajouter.",
                    ],
                ]);
            }
        }

        if ($employees > 0 && $plan?->max_employees !== null) {
            $used = $this->employeesUsed($companyId);
            if ($used + $employees > $plan->max_employees) {
                throw ValidationException::withMessages([
                    'quota.employees' => [
                        "La limite de {$plan->max_employees} employés de votre forfait est atteinte. Changez de forfait pour en ajouter.",
                    ],
                ]);
            }
        }
    }

    /** @param array<string, mixed>|null $metadata */
    public function countsTowardEmployeeQuota(int $companyId, ?array $metadata, ?string $email): bool
    {
        if (($metadata['role'] ?? null) === 'tenant') {
            return false;
        }

        if ($email === null || trim($email) === '') {
            return true;
        }

        return ! User::query()
            ->where('company_id', $companyId)
            ->where('role', 'tenant')
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->exists();
    }

    public function usersUsed(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereNotIn('role', ['employe', 'team_manager'])
            ->count();
    }

    public function employeesUsed(int $companyId): int
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('metadata->role')
                    ->orWhere('metadata->role', '<>', 'tenant');
            })
            ->whereNotExists(function ($query) use ($companyId): void {
                $query->selectRaw('1')
                    ->from('users')
                    ->where('users.company_id', $companyId)
                    ->where('users.role', 'tenant')
                    ->whereRaw('LOWER(users.email) = LOWER(employees.email)');
            })
            ->count();
    }

    public function planForCompany(Company $company): ?SubscriptionPlan
    {
        $subscription = $this->resolveInForceSubscription($company)
            ?? $this->resolveLatestSubscription($company);

        if ($subscription?->plan !== null) {
            return $subscription->plan;
        }

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
            ->where(function ($query) use ($planKey, $normalized, $code): void {
                $query->where('code', $code)
                    ->orWhere('code', $normalized)
                    ->orWhere('name', $planKey)
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first();
    }

    private function resolveInForceSubscription(Company $company): ?Subscription
    {
        $today = now()->toDateString();

        return Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveLatestSubscription(Company $company): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }
}
