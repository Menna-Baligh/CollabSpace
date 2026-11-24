<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRecordingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'duration_seconds' => $this->duration_seconds,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'download_url' => route('api.v1.meetings.recordings.download', [
                'meeting' => $this->meeting_id,
                'recording' => $this->id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
