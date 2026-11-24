<?php

namespace App\Policies;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($conversation->type === ConversationType::DIRECT) {
            return $conversation->participants()->where('user_id', $user->id)->exists();
        }

        if ($conversation->type === ConversationType::PROJECT_CHANNEL) {
            return $conversation->project_id && $user->isMemberOfProject($conversation->project_id);
        }

        if ($conversation->type === ConversationType::TEAM_CHANNEL) {
            return $conversation->team && $conversation->team->members()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can send messages or participate.
     */
    public function participate(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
