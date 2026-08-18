<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\PlatformSettingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSettingsRequest;
use App\Http\Resources\PlatformSettingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class PlatformSettingController extends Controller
{
    public function __construct(private readonly PlatformSettingService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $group = $request->query('group');

        return PlatformSettingResource::collection(
            $this->service->list(is_string($group) ? $group : null),
        );
    }

    public function update(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $updated = $this->service->updateMany($request->validated('settings'));

        return response()->json([
            'data' => PlatformSettingResource::collection($updated),
        ]);
    }
}
