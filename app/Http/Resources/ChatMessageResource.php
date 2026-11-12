<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMessage */
class ChatMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->chat_channel_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
            ],
            'attachments' => ChatAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
        ];
    }
}
