<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\ChatAccessService;
use App\Domain\Chat\Models\ChatAttachment;
use App\Domain\Chat\Models\ChatChannel;
use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatChannelResource;
use App\Http\Resources\ChatMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ChatController extends Controller
{
    public function __construct(private readonly ChatAccessService $access) {}

    public function channels(Request $request): AnonymousResourceCollection
    {
        $channels = $this->access->channelsFor($request->user());
        $channels->each(fn (ChatChannel $channel) => $channel->setRelation(
            'messages',
            $channel->messages()
                ->with(['sender:id,name', 'attachments'])
                ->latest('id')
                ->limit(1)
                ->get(),
        ));

        return ChatChannelResource::collection($channels);
    }

    public function messages(Request $request, ChatChannel $channel): AnonymousResourceCollection
    {
        $this->authorizeChannel($request, $channel);
        $messages = $channel->messages()
            ->with(['sender:id,name', 'attachments'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return ChatMessageResource::collection($messages);
    }

    public function send(Request $request, ChatChannel $channel): ChatMessageResource
    {
        $this->authorizeChannel($request, $channel);
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt',
            ],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $files = $request->file('attachments', []);
        if ($body === '' && $files === []) {
            throw ValidationException::withMessages([
                'body' => 'Ajoutez un message ou au moins un fichier.',
            ]);
        }

        $storedPaths = [];
        try {
            foreach ($files as $file) {
                $path = $file->store('chat-attachments', 'local');
                if ($path === false) {
                    throw new \RuntimeException('Impossible de stocker une pièce jointe de messagerie.');
                }
                $storedPaths[] = $path;
            }

            $message = DB::transaction(function () use ($channel, $request, $body, $files, $storedPaths) {
                $message = $channel->messages()->create([
                    'user_id' => $request->user()->id,
                    'body' => $body,
                ]);

                foreach ($files as $index => $file) {
                    $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'attachment';
                    $message->attachments()->create([
                        'disk' => 'local',
                        'path' => $storedPaths[$index],
                        'original_name' => mb_substr($originalName, 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
                }

                return $message;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        $message->load(['sender:id,name', 'attachments']);
        broadcast(new ChatMessageSent($message))->toOthers();

        return new ChatMessageResource($message);
    }

    public function downloadAttachment(Request $request, ChatAttachment $attachment): Response|JsonResponse
    {
        $attachment->loadMissing('message.channel');
        abort_unless(
            $attachment->message !== null
                && $this->access->canAccess($request->user(), $attachment->message->channel),
            Response::HTTP_NOT_FOUND,
        );

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), Response::HTTP_NOT_FOUND);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function authorizeChannel(Request $request, ChatChannel $channel): void
    {
        abort_unless($this->access->canAccess($request->user(), $channel), Response::HTTP_NOT_FOUND);
    }
}
