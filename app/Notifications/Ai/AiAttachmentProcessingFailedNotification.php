<?php

namespace App\Notifications\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class AiAttachmentProcessingFailedNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly AiMessage $message,
        private readonly AiConversation $conversation,
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
            'type' => 'ai_attachment_processing_failed',
            'title' => __('notifications.ai_attachment_processing_failed_title'),
            'body' => __('notifications.ai_attachment_processing_failed_body', [
                'file' => $this->message->attachment_name ?? __('notifications.attachment'),
            ]),
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'attachment_name' => $this->message->attachment_name,
            'url' => '/ai/conversations/'.$this->conversation->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
