<?php

namespace App\Notifications\Meetings;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class MeetingCancelledNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly Meeting $meeting,
    ) {}

    protected function getRecipientId(): int
    {
        return $this->recipient->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'meeting_cancelled',
            'title' => __('notifications.meeting_cancelled_title'),
            'body' => __('notifications.meeting_cancelled_body', [
                'title' => $this->meeting->title,
            ]),
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'url' => '/meetings',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
