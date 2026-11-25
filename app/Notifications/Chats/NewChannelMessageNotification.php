<?php

namespace App\Notifications\Chats;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class NewChannelMessageNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly ChatMessage $message,
        private readonly Conversation $conversation,
        private readonly User $sender,
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
            'type' => 'new_channel_message',
            'title' => __('notifications.new_channel_message_title', [
                'channel' => $this->conversation->name ?? __('notifications.channel'),
                'sender' => $this->sender->name,
            ]),
            'body' => $this->message->body
                ? mb_substr($this->message->body, 0, 100)
                : __('notifications.attachment_message'),
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'channel_name' => $this->conversation->name,
            'url' => '/chats/'.$this->conversation->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
