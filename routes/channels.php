<?php

use App\Models\ChatMessageThread;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('user.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function (User $user, $id) {
    $conversation = Conversation::find($id);
    if (! $conversation) {
        return false;
    }

    $service = app(ConversationService::class);

    return $service->canAccess($user, $conversation);
});

Broadcast::channel('thread.{id}', function (User $user, $id) {
    $thread = ChatMessageThread::find($id);
    if (! $thread) {
        return false;
    }

    $conversation = $thread->conversation;
    if (! $conversation) {
        return false;
    }

    $service = app(ConversationService::class);

    return $service->canAccess($user, $conversation);
});

Broadcast::channel('presence-conversation.{id}', function (User $user, $id) {
    $conversation = Conversation::find($id);
    if (! $conversation) {
        return false;
    }

    $service = app(ConversationService::class);
    if ($service->canAccess($user, $conversation)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar_url,
        ];
    }

    return false;
});

Broadcast::channel('meeting.{id}', function (User $user, $id) {
    $meeting = Meeting::find($id);
    if (! $meeting) {
        return false;
    }

    if ($user->isAdmin() || $meeting->creator_id === $user->id) {
        return true;
    }

    if ($meeting->project_id && $user->isMemberOfProject($meeting->project_id)) {
        return true;
    }

    return $meeting->participants()->where('user_id', $user->id)->exists();
});
