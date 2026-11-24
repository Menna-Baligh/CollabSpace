<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => new UserSummaryResource($this->whenLoaded('sender')),
            'body' => $this->body,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'attachments' => ChatAttachmentResource::collection($this->whenLoaded('attachments')),
            'has_thread' => (bool) $this->thread,
            'thread_id' => $this->thread?->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
