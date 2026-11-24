<?php

namespace App\Events;

use App\Http\Resources\ChatThreadReplyResource;
use App\Models\ChatThreadReply;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadReplySent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatThreadReply $reply) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('thread.'.$this->reply->thread_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'reply' => (new ChatThreadReplyResource($this->reply))->resolve(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'thread.reply.sent';
    }
}
