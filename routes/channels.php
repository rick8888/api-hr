<?php

use App\Application\Services\ChatAccessService;
use App\Domain\Chat\Models\ChatChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.channel.{channelId}', function ($user, int $channelId): bool {
    $channel = ChatChannel::query()->find($channelId);

    return $channel !== null && app(ChatAccessService::class)->canAccess($user, $channel);
});
