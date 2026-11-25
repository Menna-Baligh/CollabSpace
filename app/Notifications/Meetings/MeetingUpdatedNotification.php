<?php

namespace App\Notifications\Meetings;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class MeetingUpdatedNotification extends BaseNotification
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
            'type' => 'meeting_updated',
            'title' => __('notifications.meeting_updated_title'),
            'body' => __('notifications.meeting_updated_body', [
                'title' => $this->meeting->title,
                'start' => $this->meeting->start_time->format('Y-m-d H:i'),
            ]),
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'start_time' => $this->meeting->start_time->toIso8601String(),
            'end_time' => $this->meeting->end_time->toIso8601String(),
            'url' => '/meetings/'.$this->meeting->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
