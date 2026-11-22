<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectDetailResource extends JsonResource
{
    /**
     * Optional pre-filtered members collection.
     */
    protected mixed $filteredMembers;

    public function __construct($resource, mixed $filteredMembers = null)
    {
        parent::__construct($resource);
        $this->filteredMembers = $filteredMembers;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membersData = [];

        if ($this->filteredMembers !== null) {
            $membersData = $this->filteredMembers;
        } elseif ($this->relationLoaded('members')) {
            $membersData = ProjectMemberResource::collection($this->members);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'project_type' => $this->project_type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'deadline' => $this->deadline?->format('Y-m-d'),
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'color' => $this->color,
            'icon' => $this->icon,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'completion_percentage' => 0,
            'timeline_progress_percentage' => $this->timelineProgressPercentage(),
            'total_tasks' => 0,
            'pending_tasks' => 0,
            'in_progress_tasks' => 0,
            'completed_tasks' => 0,
            'total_tasks_count' => 0,
            'members' => $membersData,
        ];
    }
}
