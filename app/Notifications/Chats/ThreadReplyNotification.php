<?php

namespace App\Notifications\Chats;

use App\Models\ChatMessageThread;
use App\Models\ChatThreadReply;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ThreadReplyNotification extends BaseNotification
{
    public function __construct(
        private readonly User $recipient,
        private readonly ChatThreadReply $reply,
        private readonly ChatMessageThread $thread,
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
            'type' => 'thread_reply',
            'title' => __('notifications.thread_reply_title', [
                'sender' => $this->sender->name,
            ]),
            'body' => $this->reply->body
                ? mb_substr($this->reply->body, 0, 100)
                : __('notifications.attachment_message'),
            'thread_id' => $this->thread->id,
            'reply_id' => $this->reply->id,
            'conversation_id' => $this->thread->conversation_id,
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'thread_title' => $this->thread->title,
            'url' => '/chats/'.$this->thread->conversation_id.'?thread='.$this->thread->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
