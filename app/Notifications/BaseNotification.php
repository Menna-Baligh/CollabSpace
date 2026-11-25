<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    /**
     * The channels to deliver the notification on.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * The private broadcast channel for the recipient user.
     *
     * @return PrivateChannel|list<PrivateChannel>
     */
    public function broadcastOn(): PrivateChannel
    {
        // broadcastOn is called by Laravel with $notifiable already resolved;
        // however the standard signature for notifications is broadcastOn().
        // We store notifiable in the notification queue payload so the channel
        // is resolved at dispatch time via toBroadcast() / broadcastWith().
        // For the channel auth we use the existing user.{id} channel registration.
        return new PrivateChannel('user.'.$this->getRecipientId());
    }

    /**
     * The event name when broadcasting.
     */
    public function broadcastType(): string
    {
        return 'notification';
    }

    /**
     * Override in each concrete notification to return the target user ID.
     */
    abstract protected function getRecipientId(): int;
}
