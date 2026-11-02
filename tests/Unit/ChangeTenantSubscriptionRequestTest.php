<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\Subscription\ChangeTenantSubscriptionRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ChangeTenantSubscriptionRequestTest extends TestCase
{
    public function test_mobile_money_is_accepted_when_operator_and_phone_are_provided(): void
    {
        $request = new ChangeTenantSubscriptionRequest;
        $validator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'mobile_money',
            'mobile_operator' => 'orange_money',
            'phone_number' => '+243000000000',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_mobile_money_requires_operator_and_phone(): void
    {
        $validator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'mobile_money',
        ], (new ChangeTenantSubscriptionRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mobile_operator', $validator->errors()->toArray());
        $this->assertArrayHasKey('phone_number', $validator->errors()->toArray());
    }

    public function test_supported_subscription_payment_methods_remain_accepted(): void
    {
        $cashValidator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'cash',
            'reference' => 'Cash receipt 123',
        ], (new ChangeTenantSubscriptionRequest)->rules());
        $this->assertFalse($cashValidator->fails());

        $transferValidator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'bank_transfer',
            'receipt' => UploadedFile::fake()->image('transfer-proof.jpg'),
        ], (new ChangeTenantSubscriptionRequest)->rules());
        $this->assertFalse($transferValidator->fails());
    }

    public function test_bank_transfer_requires_a_valid_proof_file(): void
    {
        $validator = Validator::make([
            'plan_code' => 'premium',
            'payment_method' => 'bank_transfer',
        ], (new ChangeTenantSubscriptionRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('receipt', $validator->errors()->toArray());
    }
}
