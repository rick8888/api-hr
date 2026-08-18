<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SelfServiceProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SelfService\UpdateSelfServicePasswordRequest;
use App\Http\Requests\SelfService\UpdateSelfServiceProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SelfServiceProfileController extends Controller
{
    public function __construct(private readonly SelfServiceProfileService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->showForActor($request->user()),
        ]);
    }

    public function update(UpdateSelfServiceProfileRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->updateForActor($request->user(), $request->validated()),
        ]);
    }

    public function updatePassword(UpdateSelfServicePasswordRequest $request): JsonResponse
    {
        $this->service->updatePasswordForActor($request->user(), $request->validated());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
