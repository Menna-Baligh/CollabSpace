<?php

namespace App\Notifications\Meetings;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class MeetingProcessingCompletedNotification extends BaseNotification
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
            'type' => 'meeting_processing_completed',
            'title' => __('notifications.meeting_processing_completed_title'),
            'body' => __('notifications.meeting_processing_completed_body', [
                'title' => $this->meeting->title,
            ]),
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'url' => '/meetings/'.$this->meeting->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
