<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;

class ChatMessagePolicy
{
    /**
     * Determine whether the user can update the message.
     */
    public function update(User $user, ChatMessage $message): bool
    {
        return $user->id === $message->sender_id;
    }

    /**
     * Determine whether the user can delete the message.
     */
    public function delete(User $user, ChatMessage $message): bool
    {
        if ($user->id === $message->sender_id || $user->isAdmin()) {
            return true;
        }

        // Project Manager can moderate project conversation messages
        $conv = $message->conversation;
        if ($conv && $conv->project_id && $user->isProjectManagerOf($conv->project_id)) {
            return true;
        }

        return false;
    }
}
