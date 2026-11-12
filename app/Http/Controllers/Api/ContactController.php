<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Models\User;
use App\Notifications\ContactFormReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $recipients = User::query()
            ->where('role', 'super_admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique();

        abort_if(
            $recipients->isEmpty(),
            Response::HTTP_SERVICE_UNAVAILABLE,
            'Le formulaire de contact est momentanément indisponible.',
        );

        $notification = new ContactFormReceived($request->validated());
        foreach ($recipients as $recipient) {
            Notification::route('mail', $recipient)->notify($notification);
        }

        return response()->json([
            'message' => 'Votre demande a été envoyée.',
        ], Response::HTTP_ACCEPTED);
    }
}
