<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PaymentMethodService;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $service)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection($this->service->listActive());
    }
}
