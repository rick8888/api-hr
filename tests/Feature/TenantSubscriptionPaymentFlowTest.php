<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\InvoiceService;
use App\Application\Services\TenantSubscriptionService;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoicePayment;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Http\Controllers\Api\InvoiceController;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TenantSubscriptionPaymentFlowTest extends TestCase
{
    private string $originalDefaultConnection;

    /** @var array<string, mixed> */
    private array $originalSqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteConnection = config('database.connections.sqlite');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite' => $this->originalSqliteConnection,
        ]);

        parent::tearDown();
    }

    public function test_payment_requests_activate_only_after_payment_is_confirmed(): void
    {
        $country = Country::query()->create([
            'code' => 'TST',
            'name' => 'Testland',
            'currency' => 'EUR',
        ]);
        $company = Company::query()->create([
            'country_id' => $country->id,
            'code' => 'PAYTEST',
            'name' => 'Payment Test Company',
            'currency' => 'EUR',
            'settings' => [],
            'is_active' => false,
        ]);
        $actor = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant-payment@example.test',
            'password' => 'password',
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);
        $employee = User::query()->create([
            'name' => 'Company Employee',
            'email' => 'employee-payment@example.test',
            'password' => 'password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $this->createPlan('free', 'Free', 0, true, 0);
        $this->createPlan('premium', 'Premium', 100, false, 1);
        $this->createPlan('enterprise', 'Enterprise', 200, false, 2);
        $this->createPlan('ultimate', 'Ultimate', 300, false, 3);

        $subscriptions = app(TenantSubscriptionService::class);
        $invoices = app(InvoiceService::class);

        $initialSubscription = $subscriptions->getForActor($actor)['current'];
        $this->assertFalse($initialSubscription['has_active_subscription']);
        $this->assertSame('Suspended', $initialSubscription['status']);
        $this->assertSame('Suspended', $subscriptions->dashboardForActor($actor)['plan_status']);
        $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertForbidden();

        $transferRequest = $subscriptions->changePlanForActor(
            $actor,
            [
                'plan_code' => 'premium',
                'payment_method' => 'bank_transfer',
                'reference' => 'TRF-123',
            ],
            UploadedFile::fake()->image('transfer-proof.jpg'),
        );
        $transferInvoice = Invoice::query()->findOrFail($transferRequest['invoice_id']);

        $this->assertSame(Invoice::STATUS_PENDING, $transferInvoice->status);
        $this->assertSame('bank_transfer', $transferInvoice->payment_method);
        $this->assertNotNull($transferInvoice->payment_proof_path);
        Storage::disk('local')->assertExists($transferInvoice->payment_proof_path);
        $proofResponse = app(InvoiceController::class)->paymentProof($transferInvoice);
        $this->assertSame(200, $proofResponse->getStatusCode());
        $this->assertDatabaseCount('subscriptions', 0);

        $invoices->recordPayment($transferInvoice, [
            'amount' => 40,
            'method' => InvoicePayment::METHOD_BANK_TRANSFER,
            'reference' => 'TRF-123-PARTIAL',
        ]);

        $this->assertSame(Invoice::STATUS_PARTIAL, $transferInvoice->fresh()->status);
        $this->assertFalse($subscriptions->getForActor($actor)['current']['has_active_subscription']);
        $this->assertSame('Suspended', $subscriptions->dashboardForActor($actor)['plan_status']);
        $this->assertFalse($company->fresh()->is_active);
        $this->assertDatabaseCount('subscriptions', 0);

        $invoices->recordPayment($transferInvoice->fresh(), [
            'amount' => (float) $transferInvoice->amount - 40,
            'method' => InvoicePayment::METHOD_BANK_TRANSFER,
            'reference' => 'TRF-123',
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $transferInvoice->fresh()->status);
        $this->assertTrue($company->fresh()->is_active);
        $this->assertTrue($subscriptions->getForActor($actor)['current']['has_active_subscription']);
        $this->assertSame('Active', $subscriptions->dashboardForActor($actor)['plan_status']);
        $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertOk();
        $company->update(['is_active' => false]);
        $renewedLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertOk();
        $this->assertTrue($company->fresh()->is_active);
        $this->withToken($renewedLoginResponse->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
        $activePlan = collect($subscriptions->getForActor($actor)['plans'])
            ->firstWhere('code', 'premium');
        $this->assertSame('Current Plan', $activePlan['cta']['label']);
        $this->assertTrue($activePlan['cta']['disabled']);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'subscription_plan_id' => SubscriptionPlan::query()->where('code', 'premium')->value('id'),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $renewalRequest = $subscriptions->changePlanForActor(
            $actor,
            [
                'plan_code' => 'premium',
                'payment_method' => 'bank_transfer',
                'reference' => 'TRF-RENEWAL',
            ],
            UploadedFile::fake()->image('renewal-proof.jpg'),
        );
        $renewalInvoice = Invoice::query()->findOrFail($renewalRequest['invoice_id']);
        $this->assertSame('Renew Premium plan', $renewalInvoice->description);
        $this->assertSame(Invoice::STATUS_PENDING, $renewalInvoice->status);

        $invoices->recordPayment($renewalInvoice, [
            'amount' => (float) $renewalInvoice->amount,
            'method' => InvoicePayment::METHOD_BANK_TRANSFER,
            'reference' => 'TRF-RENEWAL',
        ]);
        $this->assertSame(Invoice::STATUS_PAID, $renewalInvoice->fresh()->status);
        $this->assertSame(2, Subscription::query()->count());

        $cashRequest = $subscriptions->changePlanForActor($actor, [
            'plan_code' => 'enterprise',
            'payment_method' => 'cash',
            'reference' => 'CASH-456',
        ], UploadedFile::fake()->image('cash-proof.jpg'));
        $cashInvoice = Invoice::query()->findOrFail($cashRequest['invoice_id']);

        $this->assertSame(Invoice::STATUS_PENDING, $cashInvoice->status);
        $this->assertSame('cash', $cashInvoice->payment_method);
        $this->assertNotNull($cashInvoice->payment_proof_path);
        Storage::disk('local')->assertExists($cashInvoice->payment_proof_path);
        $this->assertSame(2, Subscription::query()->count());

        $invoices->recordPayment($cashInvoice, [
            'amount' => (float) $cashInvoice->amount,
            'method' => InvoicePayment::METHOD_CASH,
            'reference' => 'CASH-456',
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $cashInvoice->fresh()->status);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $cashInvoice->id,
            'method' => InvoicePayment::METHOD_CASH,
            'reference' => 'CASH-456',
        ]);
        $this->assertSame(2, Subscription::query()->count());

        $mobileRequest = $subscriptions->changePlanForActor($actor, [
            'plan_code' => 'ultimate',
            'payment_method' => 'mobile_money',
            'mobile_operator' => 'orange_money',
            'phone_number' => '+243000000000',
        ], UploadedFile::fake()->image('mobile-money-proof.jpg'));
        $mobileInvoice = Invoice::query()->findOrFail($mobileRequest['invoice_id']);

        $this->assertSame(Invoice::STATUS_PENDING, $mobileInvoice->status);
        $this->assertSame('mobile_money', $mobileInvoice->payment_method);
        $this->assertNotNull($mobileInvoice->payment_proof_path);
        Storage::disk('local')->assertExists($mobileInvoice->payment_proof_path);
        $this->assertStringContainsString('Operator: orange_money', $mobileInvoice->notes);
        $this->assertStringContainsString('Phone: +243000000000', $mobileInvoice->notes);
        $this->assertSame(2, Subscription::query()->count());

        $invoices->recordPayment($mobileInvoice, [
            'amount' => (float) $mobileInvoice->amount,
            'method' => InvoicePayment::METHOD_MOBILE_MONEY,
            'reference' => 'MM-789',
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $mobileInvoice->fresh()->status);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $mobileInvoice->id,
            'method' => InvoicePayment::METHOD_MOBILE_MONEY,
            'reference' => 'MM-789',
        ]);
        $this->assertSame(2, Subscription::query()->count());
    }

    public function test_subscription_prices_and_invoices_use_the_company_currency_price(): void
    {
        $country = Country::query()->create([
            'code' => 'MAR',
            'name' => 'Morocco',
            'currency' => 'MAD',
        ]);
        $company = Company::query()->create([
            'country_id' => $country->id,
            'code' => 'MADTEST',
            'name' => 'Morocco Test Company',
            'currency' => 'MAD',
            'settings' => [],
            'is_active' => true,
        ]);
        $actor = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant-currency@example.test',
            'password' => 'password',
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);
        $plan = $this->createPlan('premium', 'Premium', 149, false, 1);
        $plan->update([
            'currency_prices' => [
                'MAD' => 1499.5,
                'EUR' => 149,
            ],
        ]);

        $subscriptions = app(TenantSubscriptionService::class);
        $premium = collect($subscriptions->getForActor($actor)['plans'])
            ->firstWhere('code', 'premium');

        $this->assertSame('MAD', $premium['currency']);
        $this->assertSame(1499.5, $premium['price_monthly']);
        $this->assertTrue($premium['price_available']);

        $request = $subscriptions->changePlanForActor($actor, [
            'plan_code' => 'premium',
            'payment_method' => 'cash',
            'reference' => 'MAD-CASH-001',
        ], UploadedFile::fake()->image('receipt.jpg'));
        $invoice = Invoice::query()->findOrFail($request['invoice_id']);

        $this->assertSame('MAD', $invoice->currency);
        $this->assertSame(1499.5, (float) $invoice->amount);
    }

    private function createPlan(
        string $code,
        string $name,
        float $price,
        bool $isFree,
        int $sortOrder,
    ): SubscriptionPlan {
        return SubscriptionPlan::query()->create([
            'code' => $code,
            'name' => $name,
            'price_monthly' => $price,
            'currency' => 'EUR',
            'is_active' => true,
            'is_free' => $isFree,
            'sort_order' => $sortOrder,
        ]);
    }
}
