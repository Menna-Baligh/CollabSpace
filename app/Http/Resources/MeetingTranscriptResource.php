<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingTranscriptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'language' => $this->language,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'full_text' => $this->full_text,
            'error_message' => $this->error_message,
            'segments' => MeetingTranscriptSegmentResource::collection($this->whenLoaded('segments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
