<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Chat\Models\ChatChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatChannel */
class ChatChannelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $latest = $this->whenLoaded('messages', fn () => $this->messages->first());

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'latest_message' => $latest ? ChatMessageResource::make($latest) : null,
        ];
    }
}
