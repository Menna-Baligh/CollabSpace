<?php

namespace App\Policies;

use App\Models\MeetingComment;
use App\Models\User;

class MeetingCommentPolicy
{
    public function update(User $user, MeetingComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    public function delete(User $user, MeetingComment $comment): bool
    {
        if ($user->id === $comment->user_id || $user->isAdmin()) {
            return true;
        }

        $meeting = $comment->meeting;

        return $meeting && $meeting->creator_id === $user->id;
    }
}
