<?php

namespace App\Events;

use App\Models\MeetingComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingCommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MeetingComment $comment
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('meeting.'.$this->comment->meeting_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'meeting_id' => $this->comment->meeting_id,
            'user' => [
                'id' => $this->comment->user->id,
                'name' => $this->comment->user->name,
                'avatar' => $this->comment->user->avatar,
            ],
            'comment' => $this->comment->comment,
            'created_at' => $this->comment->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'meeting.comment.created';
    }
}
