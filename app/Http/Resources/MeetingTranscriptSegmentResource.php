<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingTranscriptSegmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'speaker_label' => $this->speaker_label,
            'speaker' => new UserSummaryResource($this->whenLoaded('speaker')),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'text' => $this->text,
        ];
    }
}
