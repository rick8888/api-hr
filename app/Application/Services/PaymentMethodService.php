<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Billing\Models\PaymentMethod;
use Illuminate\Support\Collection;

final class PaymentMethodService
{
    /** @return Collection<int, PaymentMethod> */
    public function listActive(): Collection
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
