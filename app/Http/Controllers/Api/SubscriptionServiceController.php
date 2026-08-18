<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SubscriptionServiceCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionServiceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionServiceController extends Controller
{
    public function __construct(private readonly SubscriptionServiceCatalog $catalog)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return SubscriptionServiceResource::collection($this->catalog->list());
    }
}
