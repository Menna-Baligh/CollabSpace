<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ai_conversation_id' => $this->ai_conversation_id,
            'parent_message_id' => $this->parent_message_id,
            'role' => $this->role?->value,
            'content' => $this->content,
            'status' => $this->status?->value,
            'has_attachment' => $this->attachment_path !== null,
            'attachment_name' => $this->attachment_name,
            'attachment_mime' => $this->attachment_mime,
            'link_preview' => $this->link_preview,
            'feedback' => $this->feedback?->value,
            'provider' => $this->provider,
            'model' => $this->model,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
