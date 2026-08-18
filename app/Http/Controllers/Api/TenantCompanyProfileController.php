<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\TenantCompanyProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantCompanyProfileController extends Controller
{
    public function __construct(private readonly TenantCompanyProfileService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->service->getForActor($request->user()));
    }

    public function update(UpdateTenantProfileRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->updateForActor($request->user(), $request->validated())
        );
    }
}
