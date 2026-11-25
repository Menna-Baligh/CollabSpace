<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * Get paginated notifications for a user.
     *
     * @return LengthAwarePaginator<DatabaseNotification>
     */
    public function getNotifications(
        User $user,
        string $filter = 'all',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $user->notifications()->orderBy('created_at', 'desc');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->paginate(min($perPage, 50));
    }

    /**
     * Get the count of unread notifications.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(User $user, string $notificationId): ?DatabaseNotification
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->find($notificationId);

        if (! $notification) {
            return null;
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $notification->fresh();
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Delete a single notification.
     */
    public function deleteNotification(User $user, string $notificationId): bool
    {
        $deleted = $user->notifications()->where('id', $notificationId)->delete();

        return (bool) $deleted;
    }

    /**
     * Delete all notifications for a user.
     */
    public function deleteAllNotifications(User $user): int
    {
        return $user->notifications()->delete();
    }
}
