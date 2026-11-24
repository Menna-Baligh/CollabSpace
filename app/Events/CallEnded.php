<?php

namespace App\Events;

use App\Models\ChatCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatCall $call
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
            'status' => $this->call->status->value,
            'ended_at' => $this->call->ended_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }
}
