<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can suspend another user account.
     * Restricted to system administrators only.
     */
    public function suspend(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
