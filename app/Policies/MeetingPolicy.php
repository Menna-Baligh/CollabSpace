<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin() || $meeting->creator_id === $user->id) {
            return true;
        }

        if ($meeting->project_id && $user->isMemberOfProject($meeting->project_id)) {
            return true;
        }

        return $meeting->participants()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin() || $meeting->creator_id === $user->id) {
            return true;
        }

        if ($meeting->project_id && $user->isProjectManagerOf($meeting->project_id)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }
}
