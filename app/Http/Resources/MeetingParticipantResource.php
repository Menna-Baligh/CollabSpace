<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'status' => $this->pivot?->status,
            'has_attended' => (bool) $this->pivot?->has_attended,
            'joined_at' => $this->pivot?->joined_at,
            'left_at' => $this->pivot?->left_at,
        ];
    }
}
