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
        ]);
        $actor = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant-payment@example.test',
            'password' => 'password',
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);

        $this->createPlan('free', 'Free', 0, true, 0);
        $this->createPlan('premium', 'Premium', 100, false, 1);
        $this->createPlan('enterprise', 'Enterprise', 200, false, 2);
        $this->createPlan('ultimate', 'Ultimate', 300, false, 3);

        $subscriptions = app(TenantSubscriptionService::class);
        $invoices = app(InvoiceService::class);

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
        $this->assertDatabaseCount('subscriptions', 0);

        $invoices->recordPayment($transferInvoice, [
            'amount' => (float) $transferInvoice->amount,
            'method' => InvoicePayment::METHOD_BANK_TRANSFER,
            'reference' => 'TRF-123',
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $transferInvoice->fresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'subscription_plan_id' => SubscriptionPlan::query()->where('code', 'premium')->value('id'),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $cashRequest = $subscriptions->changePlanForActor($actor, [
            'plan_code' => 'enterprise',
            'payment_method' => 'cash',
            'reference' => 'CASH-456',
        ]);
        $cashInvoice = Invoice::query()->findOrFail($cashRequest['invoice_id']);

        $this->assertSame(Invoice::STATUS_PENDING, $cashInvoice->status);
        $this->assertSame('cash', $cashInvoice->payment_method);
        $this->assertNull($cashInvoice->payment_proof_path);
        $this->assertSame(1, Subscription::query()->count());

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
        ]);
        $mobileInvoice = Invoice::query()->findOrFail($mobileRequest['invoice_id']);

        $this->assertSame(Invoice::STATUS_PENDING, $mobileInvoice->status);
        $this->assertSame('mobile_money', $mobileInvoice->payment_method);
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
