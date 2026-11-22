<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $members = $this->relationLoaded('members')
            ? $this->members->take(4)->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => null,
            ])
            : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'deadline' => $this->deadline?->format('Y-m-d'),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'files_count' => 0,
            'members_count' => $this->members_count ?? ($this->relationLoaded('members') ? $this->members->count() : 0),
            'members_preview' => $members,
        ];
    }
}
