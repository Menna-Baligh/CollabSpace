<?php

namespace App\Policies;

use App\Models\MeetingNote;
use App\Models\User;

class MeetingNotePolicy
{
    public function update(User $user, MeetingNote $note): bool
    {
        return $user->id === $note->user_id;
    }

    public function delete(User $user, MeetingNote $note): bool
    {
        if ($user->id === $note->user_id || $user->isAdmin()) {
            return true;
        }

        $meeting = $note->meeting;

        return $meeting && $meeting->creator_id === $user->id;
    }
}
