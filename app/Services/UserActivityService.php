<?php

namespace App\Services;

use App\Enums\UserActivityType;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserActivityService
{
    /**
     * Log an activity for a user safely.
     */
    public function log(
        User $user,
        UserActivityType|string $actionType,
        string $description,
        ?Model $subject = null
    ): ?UserActivity {
        try {
            $type = $actionType instanceof UserActivityType ? $actionType : UserActivityType::from($actionType);

            return UserActivity::create([
                'user_id' => $user->id,
                'action_type' => $type,
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->getKey() : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to log user activity', [
                'user_id' => $user->id,
                'action_type' => $actionType instanceof UserActivityType ? $actionType->value : $actionType,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
