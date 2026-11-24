<?php

namespace App\Events;

use App\Models\ChatCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallInitiated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatCall $call,
        public string $joinUrl
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->call->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call->id,
            'conversation_id' => $this->call->conversation_id,
            'initiator' => [
                'id' => $this->call->initiator->id,
                'name' => $this->call->initiator->name,
                'avatar' => $this->call->initiator->avatar,
            ],
            'room_name' => $this->call->room_name,
            'type' => $this->call->type->value,
            'join_url' => $this->joinUrl,
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.initiated';
    }
}
