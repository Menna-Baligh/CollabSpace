<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'parent_message_id' => $this->parent_message_id,
            'title' => $this->title,
            'creator' => new UserSummaryResource($this->whenLoaded('creator')),
            'parent_message' => new ChatMessageResource($this->whenLoaded('parentMessage')),
            'replies_count' => $this->replies_count,
            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
