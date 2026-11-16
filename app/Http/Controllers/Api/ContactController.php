<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Platform\Models\ContactRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Models\User;
use App\Notifications\ContactFormReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $request->validated();

        ContactRequest::create([
            ...$contact,
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        $recipients = User::query()
            ->where('role', 'super_admin')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique();

        $notification = new ContactFormReceived($contact);
        foreach ($recipients as $recipient) {
            Notification::route('mail', $recipient)->notify($notification);
        }

        return response()->json([
            'message' => 'Votre demande a été envoyée.',
        ], Response::HTTP_ACCEPTED);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $status = $request->query('status');

        $items = ContactRequest::query()
            ->when(
                is_string($status) && in_array($status, ContactRequest::STATUSES, true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest()
            ->limit(500)
            ->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'unread' => ContactRequest::query()->where('status', 'new')->count(),
            ],
        ]);
    }

    public function update(Request $request, ContactRequest $contactRequest): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ContactRequest::STATUSES)],
        ]);

        $contactRequest->status = $validated['status'];
        if ($validated['status'] !== 'new' && $contactRequest->read_at === null) {
            $contactRequest->read_at = now();
        }
        if ($validated['status'] === 'new') {
            $contactRequest->read_at = null;
        }
        $contactRequest->save();

        return response()->json(['data' => $contactRequest]);
    }

    public function destroy(Request $request, ContactRequest $contactRequest): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $contactRequest->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'super_admin', Response::HTTP_FORBIDDEN);
    }
}
