<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Billing\InvoiceData;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentMethod;
use App\Domain\Company\Models\Company;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class TenantSubscriptionService
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

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TenantPlanQuotaService $planQuotas,
    ) {}

    /** @return array{current: array<string, mixed>, plans: array<int, array<string, mixed>>, history: array<int, array<string, mixed>>} */
    public function cancelForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);

        $hadAny = Subscription::query()
            ->where('company_id', $company->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->exists();

        if (! $hadAny) {
            throw new AuthorizationException('Aucun abonnement actif à annuler.');
        }

        // Annule période en cours + éventuellement planifié (max 2 slots → zéro).
        $this->pruneToSubscriptionSlots($company, null, null);

        return $this->getForActor($actor);
    }

    /**
     * Création d'une demande de changement de plan avec paiement manuel.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function changePlanForActor(User $actor, array $payload, ?UploadedFile $receipt = null): array
    {
        $company = $this->resolveCompany($actor);
        $planCode = trim((string) ($payload['plan_code'] ?? ''));
        $paymentMethod = (string) ($payload['payment_method'] ?? '');
        if (! in_array($paymentMethod, [
            PaymentMethod::CODE_BANK_TRANSFER,
            PaymentMethod::CODE_MOBILE_MONEY,
            PaymentMethod::CODE_CASH,
        ], true)) {
            throw new InvalidArgumentException('Selected payment method is not available.');
        }

        if ($paymentMethod === PaymentMethod::CODE_BANK_TRANSFER && $receipt === null) {
            throw new InvalidArgumentException('A bank transfer receipt is required.');
        }

        if ($paymentMethod === PaymentMethod::CODE_CASH && $receipt !== null) {
            throw new InvalidArgumentException('Cash payment requests do not accept a receipt upload.');
        }

        if ($paymentMethod === PaymentMethod::CODE_MOBILE_MONEY && $receipt !== null) {
            throw new InvalidArgumentException('Mobile money requests do not accept a receipt upload.');
        }

        $newPlan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where(function ($query) use ($planCode) {
                $query->where('code', $planCode)
                    ->orWhere('id', ctype_digit($planCode) ? (int) $planCode : 0);
            })
            ->first();

        if ($newPlan === null) {
            throw new InvalidArgumentException('Selected plan is not available.');
        }

        $currentSubscription = $this->resolveDisplaySubscription($company);
        $currentPlan = $currentSubscription?->plan ?? $this->resolveCurrentPlan($company);

        if ($currentPlan !== null && $currentPlan->id === $newPlan->id) {
            throw new InvalidArgumentException('You are already on this plan.');
        }

        $amount = (float) $newPlan->price_monthly;
        $currency = strtoupper((string) ($newPlan->currency ?: $company->currency ?: 'USD'));
        $today = now()->toDateString();
        $periodEnd = now()->addMonth()->toDateString();

        $description = $currentPlan !== null && ($currentPlan->sort_order > $newPlan->sort_order)
            ? 'Change to '.$newPlan->name.' plan (Downgrade)'
            : 'Change to '.$newPlan->name.' plan';

        $notes = $this->buildPaymentNotes($payload, $paymentMethod);

        $proofPath = null;
        try {
            return DB::transaction(function () use (
                $actor,
                $company,
                $newPlan,
                $paymentMethod,
                $amount,
                $currency,
                $today,
                $periodEnd,
                $description,
                $notes,
                $receipt,
                &$proofPath,
            ) {
                $invoice = $this->invoiceService->create(InvoiceData::fromArray([
                    'company_id' => $company->id,
                    'plan_code' => $newPlan->code,
                    'description' => $description,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => Invoice::STATUS_PENDING,
                    'issue_date' => $today,
                    'due_date' => $today,
                    'period_start' => $today,
                    'period_end' => $periodEnd,
                    'payment_method' => $paymentMethod,
                    'notes' => $notes,
                ]));

                if ($receipt !== null) {
                    $storedPath = $receipt->store(
                        'subscription-payment-proofs/'.$company->id,
                        'local',
                    );
                    if (! is_string($storedPath)) {
                        throw new RuntimeException('Unable to store the bank transfer receipt.');
                    }

                    $proofPath = $storedPath;
                    $invoice->update(['payment_proof_path' => $proofPath]);
                }

                return [
                    'status' => 'pending_approval',
                    'message' => match ($paymentMethod) {
                        PaymentMethod::CODE_BANK_TRANSFER => 'Transfer request and receipt submitted. The payment will be verified before your plan is activated.',
                        PaymentMethod::CODE_MOBILE_MONEY => 'Mobile money payment request submitted. Our team will verify the payment before activating your plan.',
                        default => 'Cash payment request submitted. The plan will be activated after the team confirms receipt.',
                    },
                    'payment_method' => $paymentMethod,
                    'simulation' => false,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'subscription' => $this->getForActor($actor),
                ];
            });
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    private function buildPaymentNotes(array $payload, string $paymentMethod): string
    {
        $parts = ['Payment method: '.$paymentMethod];

        if ($paymentMethod === PaymentMethod::CODE_CASH && ! empty($payload['reference'])) {
            $parts[] = 'Reference: '.$payload['reference'];
        }

        if ($paymentMethod === PaymentMethod::CODE_BANK_TRANSFER && ! empty($payload['reference'])) {
            $parts[] = 'Transfer reference: '.$payload['reference'];
        }

        if ($paymentMethod === PaymentMethod::CODE_MOBILE_MONEY) {
            $parts[] = 'Operator: '.($payload['mobile_operator'] ?? '—');
            $parts[] = 'Phone: '.($payload['phone_number'] ?? '—');
        }

        return implode(' | ', $parts);
    }

    /**
     * Active le plan lié à une facture passée en paid (admin / paiement / settle).
     * No-op si pas de plan_code exploitable.
     *
     * Si une subscription est encore en cours, la nouvelle commence à sa ends_at
     * (durée issue de la facture si period_start/end fournis, sinon +1 mois).
     */
    public function activateFromPaidInvoice(Invoice $invoice): void
    {
        $planCode = trim((string) ($invoice->plan_code ?? ''));
        if ($planCode === '') {
            return;
        }

        $company = Company::query()->find($invoice->company_id);
        if ($company === null) {
            return;
        }

        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where(function ($query) use ($planCode) {
                $normalized = strtolower($planCode);
                $aliased = self::PLAN_ALIASES[$normalized] ?? $normalized;
                $query->where('code', $planCode)
                    ->orWhere('code', $normalized)
                    ->orWhere('code', $aliased)
                    ->orWhere('id', ctype_digit($planCode) ? (int) $planCode : 0);
            })
            ->first();

        if ($plan === null) {
            return;
        }

        $startsAt = optional($invoice->period_start)->toDateString()
            ?? optional($invoice->issue_date)->toDateString();
        $endsAt = optional($invoice->period_end)->toDateString();

        $this->activateForCompany($company, $plan, $startsAt, $endsAt);
    }

    /**
     * Crée / met à jour le slot d'abonnement pour la company.
     *
     * Règle stricte : au plus 2 subscriptions par company —
     * 1) celle qui court (période en force)
     * 2) celle qui arrive (planifiée), si besoin
     *
     * S'il y a une période en cours, la nouvelle commence à sa ends_at.
     * Un second slot déjà planifié est remplacé (pas une 3e ligne).
     */
    public function activateForCompany(
        Company $company,
        SubscriptionPlan $plan,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): void {
        $today = now()->toDateString();
        $periodDays = $this->periodLengthInDays($startsAt, $endsAt);
        $inForce = $this->resolveInForceSubscription($company);

        $chainAfterCurrent = false;
        if ($inForce?->ends_at !== null) {
            $currentEnd = $inForce->ends_at->toDateString();
            if ($currentEnd >= $today) {
                $startsAt = $currentEnd;
                $chainAfterCurrent = true;
            }
        }

        if (! $chainAfterCurrent) {
            $startsAt ??= $today;
        }

        $endsAt = $periodDays !== null
            ? Carbon::parse($startsAt)->addDays($periodDays)->toDateString()
            : Carbon::parse($startsAt)->addMonth()->toDateString();

        if ($chainAfterCurrent && $inForce !== null) {
            // Slot "qui court" + slot "qui arrive" (upsert, jamais une 3e active).
            $next = $this->upsertUpcomingSubscription($company, $plan, $startsAt, $endsAt, $inForce->id);
            $this->pruneToSubscriptionSlots($company, $inForce->id, $next->id);
        } else {
            // Activation immédiate : un seul slot en vigueur.
            $current = $this->upsertImmediateSubscription($company, $plan, $startsAt, $endsAt);
            $this->pruneToSubscriptionSlots($company, $current->id, null);
        }

        // settings.plan suit la période en force (pas le plan futur planifié).
        $display = $this->resolveInForceSubscription($company);
        $display?->loadMissing('plan');
        $settings = is_array($company->settings) ? $company->settings : [];
        $settings['plan'] = $display?->plan?->code ?? $plan->code;
        $company->update(['settings' => $settings]);
        $company->refresh();
    }

    /**
     * Remplace ou crée le seul abonnement planifié (starts_at futur / après l’en cours).
     */
    private function upsertUpcomingSubscription(
        Company $company,
        SubscriptionPlan $plan,
        string $startsAt,
        string $endsAt,
        int $inForceId,
    ): Subscription {
        $queued = $this->resolveQueuedSubscription($company);

        $payload = [
            'subscription_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'cancelled_at' => null,
        ];

        if ($queued !== null && $queued->id !== $inForceId) {
            $queued->update($payload);

            return $queued->fresh() ?? $queued;
        }

        return Subscription::query()->create([
            'company_id' => $company->id,
            ...$payload,
        ]);
    }

    /**
     * Un seul abonnement actif immédiat (remplace n’importe quel slot existant).
     */
    private function upsertImmediateSubscription(
        Company $company,
        SubscriptionPlan $plan,
        string $startsAt,
        string $endsAt,
    ): Subscription {
        // Préfère réutiliser la ligne en force, sinon la plus récente active, sinon create.
        $slot = $this->resolveInForceSubscription($company)
            ?? Subscription::query()
                ->where('company_id', $company->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->first();

        $payload = [
            'subscription_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'cancelled_at' => null,
        ];

        if ($slot !== null) {
            $slot->update($payload);

            return $slot->fresh() ?? $slot;
        }

        return Subscription::query()->create([
            'company_id' => $company->id,
            ...$payload,
        ]);
    }

    /**
     * Ne conserve que 0–2 lignes : current (+ next optionnel). Le reste est supprimé.
     */
    private function pruneToSubscriptionSlots(Company $company, ?int $currentId, ?int $nextId): void
    {
        $keepIds = array_values(array_unique(array_filter(
            [$currentId, $nextId],
            static fn (?int $id): bool => $id !== null && $id > 0,
        )));

        $query = Subscription::query()->where('company_id', $company->id);

        if ($keepIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('id', $keepIds)->delete();
    }

    /** Durée en jours entre deux dates (null si indéfinissable). */
    private function periodLengthInDays(?string $startsAt, ?string $endsAt): ?int
    {
        if ($startsAt === null || $endsAt === null) {
            return null;
        }

        $days = (int) Carbon::parse($startsAt)->diffInDays(Carbon::parse($endsAt), false);

        return $days > 0 ? $days : null;
    }

    /** @return array{current: array<string, mixed>, plans: array<int, array<string, mixed>>, history: array<int, array<mixed>>} */
    public function getForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);
        $settings = is_array($company->settings) ? $company->settings : [];
        $subdomain = (string) ($settings['subdomain'] ?? strtolower((string) $company->code));

        $currentSubscription = $this->resolveDisplaySubscription($company);
        $currentPlan = $currentSubscription?->plan ?? $this->resolveCurrentPlan($company);

        $allPlans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availablePlans = $allPlans->where('is_free', false)->values();

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->get();

        $planByCode = $allPlans->keyBy('code');

        return [
            'current' => $this->formatCurrent($company, $currentPlan, $subdomain, $currentSubscription, $invoices),
            'plans' => $availablePlans
                ->map(fn (SubscriptionPlan $plan) => $this->formatPlanCard($plan, $currentPlan))
                ->values()
                ->all(),
            'history' => $invoices
                ->map(fn (Invoice $invoice) => $this->formatHistoryRow($invoice, $planByCode))
                ->values()
                ->all(),
        ];
    }

    /**
     * Tableau de bord du portail tenant.
     *
     * @return array<string, mixed>
     */
    public function dashboardForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);
        $settings = is_array($company->settings) ? $company->settings : [];
        $subdomain = (string) ($settings['subdomain'] ?? strtolower((string) $company->code));

        $subscription = $this->resolveDisplaySubscription($company);
        $plan = $subscription?->plan ?? $this->resolveCurrentPlan($company);

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->get();

        $pendingInvoice = $invoices->first(
            fn (Invoice $invoice) => in_array($invoice->status, [
                Invoice::STATUS_PENDING,
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
            ], true)
        );

        $nextBilling = $subscription?->ends_at
            ?? $pendingInvoice?->due_date
            ?? $invoices->first()?->period_end;

        $subscriptionStatus = match ($subscription?->status) {
            Subscription::STATUS_CANCELLED => 'Cancelled',
            Subscription::STATUS_EXPIRED => 'Expired',
            default => $company->is_active ? 'Active' : 'Suspended',
        };

        $usersCount = $this->planQuotas->usersUsed((int) $company->id);

        $nextPaymentAmount = $pendingInvoice !== null
            ? $this->formatPrice((float) $pendingInvoice->amount, $pendingInvoice->currency)
            : ($plan !== null
                ? $this->formatPrice((float) $plan->price_monthly, $plan->currency)
                : '—');

        $firstName = trim((string) strtok((string) $actor->name, ' '));
        if ($firstName === '') {
            $firstName = 'there';
        }

        $usersAllowed = $plan?->max_users;

        return [
            'product_name' => 'Bravo RH',
            'first_name' => $firstName,
            'application_url' => 'https://'.$subdomain.'.opencorebs.com',
            'current_plan' => $plan?->name ?? (string) ($settings['plan'] ?? '—'),
            'plan_status' => $subscriptionStatus,
            'next_payment_amount' => $nextPaymentAmount,
            'next_payment_date' => $nextBilling ? $this->formatDate($nextBilling) : '—',
            'users_count' => $usersCount,
            'users_allowed' => $usersAllowed,
            'users_allowed_label' => $usersAllowed === null ? 'Unlimited' : (string) $usersAllowed,
            'price_label' => $plan !== null
                ? $this->formatPrice((float) $plan->price_monthly, $plan->currency).'/Monthly'
                : '—',
            'started_date' => $this->formatDate($subscription?->starts_at ?? $company->created_at),
            'next_billing_date' => $nextBilling ? $this->formatDate($nextBilling) : '—',
        ];
    }

    /**
     * Usage / quotas du plan courant pour le portail tenant.
     *
     * @return array{plan: array<string, mixed>, metrics: array<int, array<string, mixed>>}
     */
    public function usageForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);
        $subscription = $this->resolveDisplaySubscription($company);
        $plan = $subscription?->plan ?? $this->resolveCurrentPlan($company);

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->get();

        $pendingInvoice = $invoices->first(
            fn (Invoice $invoice) => in_array($invoice->status, [Invoice::STATUS_PENDING, Invoice::STATUS_DRAFT], true)
        );

        $nextBilling = $subscription?->ends_at
            ?? $pendingInvoice?->due_date
            ?? $invoices->first()?->period_end;

        $usersUsed = $this->planQuotas->usersUsed((int) $company->id);
        $employeesUsed = $this->planQuotas->employeesUsed((int) $company->id);

        // Stockage réel non encore instrumenté (pas de taille fichier en base).
        $storageUsedGb = 0.0;

        return [
            'plan' => [
                'name' => $plan?->name ?? '—',
                'tagline' => $plan?->tagline ?? '',
                'price' => $plan !== null
                    ? $this->formatPrice((float) $plan->price_monthly, $plan->currency)
                    : '—',
                'cadence' => 'per monthly',
                'renews_on' => $nextBilling ? $this->formatDate($nextBilling) : null,
            ],
            'metrics' => [
                $this->metric(
                    'users',
                    'Users',
                    'user',
                    $usersUsed,
                    $plan?->max_users,
                    null,
                ),
                $this->metric(
                    'employees',
                    'Employees',
                    'user',
                    $employeesUsed,
                    $plan?->max_employees,
                    null,
                ),
                $this->metric(
                    'storage',
                    'Storage',
                    'storage',
                    $storageUsedGb,
                    $plan?->storage_gb,
                    'GB',
                ),
            ],
        ];
    }

    /**
     * @return array{id: string, label: string, icon: string, used: float|int, limit: int|null, unit: string|null, display: string, warning_threshold: float}
     */
    private function metric(
        string $id,
        string $label,
        string $icon,
        float|int $used,
        ?int $limit,
        ?string $unit,
    ): array {
        $display = $this->formatMetricDisplay($used, $limit, $unit);

        return [
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
            'used' => $used,
            'limit' => $limit,
            'unit' => $unit,
            'display' => $display,
            'warning_threshold' => 0.8,
        ];
    }

    private function formatMetricDisplay(float|int $used, ?int $limit, ?string $unit): string
    {
        if ($unit === 'GB') {
            $usedLabel = number_format((float) $used, 2).' GB';
            $limitLabel = $limit === null ? 'Unlimited' : $limit.' GB';

            return $usedLabel.' / '.$limitLabel;
        }

        if ($limit === null) {
            return $used.' / Unlimited';
        }

        return $used.' / '.$limit;
    }

    /**
     * Factures de la société du tenant (portail).
     *
     * @return array<int, array<string, mixed>>
     */
    public function invoicesForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        return Invoice::query()
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $invoice) => $this->formatTenantInvoice($invoice, $plans))
            ->values()
            ->all();
    }

    /**
     * Détail d'une facture pour le portail tenant.
     *
     * @return array<string, mixed>
     */
    public function invoiceForActor(User $actor, int $invoiceId): array
    {
        $company = $this->resolveCompany($actor);

        $invoice = Invoice::query()
            ->where('company_id', $company->id)
            ->where('id', $invoiceId)
            ->firstOrFail();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        return $this->formatTenantInvoiceDetail($invoice, $company, $plans);
    }

    /**
     * Génère le PDF d'une facture tenant.
     */
    public function downloadInvoicePdfForActor(User $actor, int $invoiceId): Response
    {
        $data = $this->invoiceForActor($actor, $invoiceId);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($data['invoice_number'] ?? 'invoice-'.$invoiceId)).'.pdf';

        return Pdf::loadView('invoices.tenant-invoice', [
            'invoice' => $data,
        ])
            ->setPaper('a4')
            ->download($filename);
    }

    /**
     * Historique de paiements / KPIs pour le portail tenant.
     *
     * @return array{kpis: array<string, mixed>, payments: array<int, array<string, mixed>>}
     */
    public function billingsForActor(User $actor): array
    {
        $company = $this->resolveCompany($actor);
        $currency = strtoupper((string) ($company->currency ?: 'USD'));

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        $payments = $invoices
            ->map(fn (Invoice $invoice) => $this->formatTenantPayment($invoice, $plans))
            ->values()
            ->all();

        $paidValue = 0.0;
        $pendingValue = 0.0;

        foreach ($invoices as $invoice) {
            if (strtoupper((string) $invoice->currency) !== $currency) {
                continue;
            }

            $amount = (float) $invoice->amount;

            if ($invoice->status === Invoice::STATUS_PAID) {
                $paidValue += $amount;
            } elseif (in_array($invoice->status, [
                Invoice::STATUS_PENDING,
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
            ], true)) {
                $pendingValue += $amount;
            }
        }

        return [
            'kpis' => [
                'paid' => $this->formatPrice($paidValue, $currency),
                'paid_value' => round($paidValue, 2),
                'pending' => $this->formatPrice($pendingValue, $currency),
                'pending_value' => round($pendingValue, 2),
                'count' => $invoices->count(),
                'currency' => $currency,
            ],
            'payments' => $payments,
        ];
    }

    /**
     * @param  Collection<string, SubscriptionPlan>  $plansByCode
     * @return array<string, mixed>
     */
    private function formatTenantPayment(Invoice $invoice, Collection $plansByCode): array
    {
        $base = $this->formatTenantInvoice($invoice, $plansByCode);

        return [
            'id' => $invoice->id,
            'invoice_id' => $invoice->id,
            'payment_id' => sprintf('#%06d', $invoice->id),
            'date' => $base['date'],
            'description' => $base['description'],
            'amount' => $base['amount'],
            'amount_value' => $base['amount_value'],
            'currency' => $base['currency'],
            'payment_method' => $invoice->payment_method,
            'status' => $this->mapBillingStatus($invoice->status),
            'status_code' => $invoice->status,
            'invoice_number' => $invoice->invoice_number,
        ];
    }

    private function mapBillingStatus(string $status): string
    {
        return match ($status) {
            Invoice::STATUS_PAID => 'Paid',
            Invoice::STATUS_PENDING, Invoice::STATUS_DRAFT, Invoice::STATUS_PARTIAL => 'Pending',
            Invoice::STATUS_OVERDUE => 'Overdue',
            Invoice::STATUS_CANCELLED => 'Failed',
            default => ucfirst($status),
        };
    }

    /**
     * @param  Collection<string, SubscriptionPlan>  $plansByCode
     * @return array<string, mixed>
     */
    private function formatTenantInvoice(Invoice $invoice, Collection $plansByCode): array
    {
        $plan = $invoice->plan_code ? $plansByCode->get($invoice->plan_code) : null;
        $description = trim((string) ($invoice->description ?? ''));

        if ($description === '') {
            $planName = $plan?->name ?? ($invoice->plan_code ?: 'subscription');
            $description = 'Payment for '.$planName.' plan';
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'date' => optional($invoice->issue_date)->toDateString()
                ?? optional($invoice->paid_at)->toDateString()
                ?? optional($invoice->created_at)->toDateString(),
            'description' => $description,
            'amount' => $this->formatPrice((float) $invoice->amount, $invoice->currency),
            'amount_value' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $this->mapHistoryStatus($invoice->status),
            'status_code' => $invoice->status,
            'payment_method' => $invoice->payment_method,
            'pdf_url' => null,
        ];
    }

    /**
     * @param  Collection<string, SubscriptionPlan>  $plansByCode
     * @return array<string, mixed>
     */
    private function formatTenantInvoiceDetail(
        Invoice $invoice,
        Company $company,
        Collection $plansByCode,
    ): array {
        $base = $this->formatTenantInvoice($invoice, $plansByCode);
        $plan = $invoice->plan_code ? $plansByCode->get($invoice->plan_code) : null;
        $settings = is_array($company->settings) ? $company->settings : [];
        $address = is_array($settings['address'] ?? null) ? $settings['address'] : [];

        $periodStart = $invoice->period_start ?? $invoice->issue_date;
        $periodEnd = $invoice->period_end ?? $invoice->due_date;
        $period = null;
        if ($periodStart && $periodEnd) {
            $period = $this->formatDate($periodStart).' – '.$this->formatDate($periodEnd);
        }

        $amount = (float) $invoice->amount;
        $amountPaid = (float) $invoice->amount_paid;
        $balance = max(0, round($amount - $amountPaid, 2));

        return array_merge($base, [
            'due_date' => optional($invoice->due_date)->toDateString(),
            'paid_at' => optional($invoice->paid_at)->toDateString(),
            'period' => $period,
            'period_start' => optional($periodStart)->toDateString(),
            'period_end' => optional($periodEnd)->toDateString(),
            'notes' => $invoice->notes,
            'plan_name' => $plan?->name,
            'plan_code' => $invoice->plan_code,
            'amount_paid' => $this->formatPrice($amountPaid, $invoice->currency),
            'amount_paid_value' => $amountPaid,
            'balance' => $this->formatPrice($balance, $invoice->currency),
            'balance_value' => $balance,
            'line_items' => [
                [
                    'description' => $base['description'],
                    'quantity' => 1,
                    'unit_price' => $base['amount'],
                    'total' => $base['amount'],
                ],
            ],
            'issuer' => [
                'name' => 'OpenCore Business Suite',
                'email' => 'billing@opencorebs.com',
                'website' => 'https://opencorebs.com',
            ],
            'bill_to' => [
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'email' => $company->email,
                'phone' => $company->phone,
                'tax_id' => $settings['vat_number'] ?? $company->tax_id,
                'street' => (string) ($address['street'] ?? $company->address ?? ''),
                'city' => (string) ($address['city'] ?? ''),
                'state' => (string) ($address['state'] ?? ''),
                'postal_code' => (string) ($address['postal_code'] ?? ''),
                'country' => $company->country?->name,
            ],
        ]);
    }

    private function resolveCompany(User $actor): Company
    {
        if ($actor->company_id === null || ! in_array($actor->role, ['tenant', 'admin'], true)) {
            throw new AuthorizationException('Accès non autorisé à l\'abonnement.');
        }

        return Company::query()->with('country')->findOrFail($actor->company_id);
    }

    /**
     * Abonnement réellement en vigueur aujourd'hui (status active + période courante).
     */
    private function resolveInForceSubscription(Company $company): ?Subscription
    {
        $today = now()->toDateString();

        return Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Unique abonnement planifié (starts_at dans le futur).
     */
    private function resolveQueuedSubscription(Company $company): ?Subscription
    {
        $today = now()->toDateString();

        return Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('starts_at', '>', $today)
            ->orderBy('starts_at')
            ->orderBy('id')
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

    /**
     * Plan / abonnement affiché comme "current" : période en force, sinon le plus récent.
     */
    private function resolveDisplaySubscription(Company $company): ?Subscription
    {
        return $this->resolveInForceSubscription($company)
            ?? $this->resolveLatestSubscription($company);
    }

    private function resolveCurrentPlan(Company $company): ?SubscriptionPlan
    {
        return $this->planQuotas->planForCompany($company);
    }

    /** @param Collection<int, Invoice> $invoices */
    private function formatCurrent(
        Company $company,
        ?SubscriptionPlan $plan,
        string $subdomain,
        ?Subscription $subscription,
        Collection $invoices,
    ): array {
        $pendingInvoice = $invoices->first(
            fn (Invoice $invoice) => in_array($invoice->status, [Invoice::STATUS_PENDING, Invoice::STATUS_DRAFT], true)
        );

        $nextBilling = $subscription?->ends_at
            ?? $pendingInvoice?->due_date
            ?? $invoices->first()?->period_end;

        $subscriptionStatus = match ($subscription?->status) {
            Subscription::STATUS_CANCELLED => 'Cancelled',
            Subscription::STATUS_EXPIRED => 'Expired',
            default => $company->is_active ? 'Active' : 'Suspended',
        };

        return [
            'plan_name' => $plan?->name ?? (string) ((is_array($company->settings) ? $company->settings['plan'] : null) ?? '—'),
            'plan_code' => $plan?->code,
            'status' => $subscriptionStatus,
            'price' => $plan !== null
                ? $this->formatPrice((float) $plan->price_monthly, $plan->currency).'/monthly'
                : '—',
            'started_date' => $this->formatDate($subscription?->starts_at ?? $company->created_at),
            'application_url' => 'https://'.$subdomain.'.opencorebs.com',
            'next_billing_date' => $nextBilling ? $this->formatDate($nextBilling) : '—',
            'features' => $plan !== null ? $this->buildFeatures($plan) : [],
            'can_cancel' => $subscription !== null && $subscription->status === Subscription::STATUS_ACTIVE,
        ];
    }

    private function formatPlanCard(SubscriptionPlan $plan, ?SubscriptionPlan $currentPlan): array
    {
        $isCurrent = $currentPlan !== null && $currentPlan->id === $plan->id;
        $currentOrder = $currentPlan?->sort_order ?? 0;

        $ctaLabel = 'Choose Plan';
        if ($isCurrent) {
            $ctaLabel = 'Current Plan';
        } elseif ($plan->sort_order > $currentOrder) {
            $ctaLabel = 'Upgrade';
        } elseif ($plan->sort_order < $currentOrder) {
            $ctaLabel = 'Downgrade';
        }

        return [
            'id' => $plan->code,
            'plan_id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price' => $this->formatPrice((float) $plan->price_monthly, $plan->currency),
            'price_monthly' => (float) $plan->price_monthly,
            'currency' => $plan->currency,
            'cadence' => '/monthly',
            'tagline' => $plan->tagline,
            'badge' => $plan->is_highlighted ? 'Most Popular' : null,
            'highlighted' => (bool) $plan->is_highlighted,
            'features' => $this->buildFeatures($plan),
            'is_current' => $isCurrent,
            'cta' => [
                'label' => $ctaLabel,
                'disabled' => $isCurrent,
                'variant' => $isCurrent ? null : 'primary',
            ],
        ];
    }

    /** @param Collection<string, SubscriptionPlan> $planByCode */
    private function formatHistoryRow(Invoice $invoice, Collection $planByCode): array
    {
        $plan = $invoice->plan_code ? $planByCode->get($invoice->plan_code) : null;
        $periodStart = $invoice->period_start ?? $invoice->issue_date;
        $periodEnd = $invoice->period_end ?? $invoice->due_date;

        $period = '—';
        if ($periodStart && $periodEnd) {
            $period = $this->formatDate($periodStart).' - '.$this->formatDate($periodEnd);
        }

        return [
            'id' => $invoice->id,
            'plan' => $plan?->name ?? ($invoice->plan_code ?? $invoice->description ?? '—'),
            'status' => $this->mapHistoryStatus($invoice->status),
            'period' => $period,
            'amount' => $this->formatPrice((float) $invoice->amount, $invoice->currency),
        ];
    }

    /** @return array<int, string> */
    private function buildFeatures(SubscriptionPlan $plan): array
    {
        $features = [];

        $features[] = $plan->max_users === null
            ? 'Unlimited users'
            : 'Up to '.$plan->max_users.' users';

        $features[] = $plan->max_employees === null
            ? 'Unlimited employees'
            : 'Up to '.$plan->max_employees.' employees';

        $features[] = $plan->storage_gb === null
            ? 'Unlimited storage'
            : $plan->storage_gb.' GB storage';

        $moduleCount = count($plan->included_services ?? []);
        $features[] = $moduleCount.' module'.($moduleCount > 1 ? 's' : '').' included';

        return $features;
    }

    private function formatPrice(float $amount, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'EUR' => '€',
            'USD' => '$',
            'MAD' => 'MAD ',
            'XOF' => 'XOF ',
            default => strtoupper($currency).' ',
        };

        return $symbol.number_format($amount, 2);
    }

    private function formatDate(mixed $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Carbon::parse($date)->format('M j, Y');
    }

    private function mapHistoryStatus(string $status): string
    {
        return match ($status) {
            Invoice::STATUS_PAID => 'Paid',
            Invoice::STATUS_PENDING => 'Pending',
            Invoice::STATUS_PARTIAL => 'Partial',
            Invoice::STATUS_OVERDUE => 'Overdue',
            Invoice::STATUS_CANCELLED => 'Cancelled',
            Invoice::STATUS_DRAFT => 'Draft',
            default => ucfirst($status),
        };
    }
}
