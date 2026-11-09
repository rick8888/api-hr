<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\Subscription\ChangeTenantSubscriptionRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ChangeTenantSubscriptionRequestTest extends TestCase
{
    public function test_mobile_money_is_accepted_with_operator_phone_and_payment_proof(): void
    {
        $request = new ChangeTenantSubscriptionRequest;
        $validator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'mobile_money',
            'mobile_operator' => 'orange_money',
            'phone_number' => '+243000000000',
            'receipt' => UploadedFile::fake()->image('mobile-money-proof.jpg'),
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_mobile_money_requires_operator_and_phone(): void
    {
        $validator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'mobile_money',
            'receipt' => UploadedFile::fake()->image('mobile-money-proof.jpg'),
        ], (new ChangeTenantSubscriptionRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mobile_operator', $validator->errors()->toArray());
        $this->assertArrayHasKey('phone_number', $validator->errors()->toArray());
    }

    public function test_all_supported_subscription_payment_methods_require_proof(): void
    {
        $cashValidator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'cash',
            'reference' => 'Cash receipt 123',
            'receipt' => UploadedFile::fake()->image('cash-proof.jpg'),
        ], (new ChangeTenantSubscriptionRequest)->rules());
        $this->assertFalse($cashValidator->fails());

        foreach (['bank_transfer', 'cash', 'mobile_money'] as $method) {
            $validator = Validator::make([
                'plan_code' => 'premium',
                'payment_method' => $method,
            ], (new ChangeTenantSubscriptionRequest)->rules());

            $this->assertTrue($validator->fails(), "Expected {$method} to require a proof file.");
            $this->assertArrayHasKey('receipt', $validator->errors()->toArray());
        }
    }
}
