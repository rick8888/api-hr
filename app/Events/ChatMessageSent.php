<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Chat\Models\ChatMessage;
use App\Http\Resources\ChatMessageResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.channel.'.$this->message->chat_channel_id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message' => (new ChatMessageResource($this->message))->resolve(),
        ];
    }
}
