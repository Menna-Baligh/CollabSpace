<?php

namespace App\Policies;

use App\Models\ChatMessageThread;
use App\Models\ChatThreadReply;
use App\Models\User;

class ChatThreadPolicy
{
    public function view(User $user, ChatMessageThread $thread): bool
    {
        return (new ConversationPolicy)->view($user, $thread->conversation);
    }

    public function reply(User $user, ChatMessageThread $thread): bool
    {
        return $this->view($user, $thread);
    }

    public function updateReply(User $user, ChatThreadReply $reply): bool
    {
        return $user->id === $reply->sender_id;
    }

    public function deleteReply(User $user, ChatThreadReply $reply): bool
    {
        if ($user->id === $reply->sender_id || $user->isAdmin()) {
            return true;
        }

        $conv = $reply->thread?->conversation;
        if ($conv && $conv->project_id && $user->isProjectManagerOf($conv->project_id)) {
            return true;
        }

        return false;
    }
}
