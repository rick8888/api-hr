<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\WhatsAppSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\UpdateWhatsAppSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppSettingsController extends Controller
{
    public function __construct(private readonly WhatsAppSettingsService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getForActor($request->user()),
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->updateForActor($request->user(), $request->validated()),
            'message' => 'Configuration WhatsApp enregistrée.',
        ]);
    }
}
