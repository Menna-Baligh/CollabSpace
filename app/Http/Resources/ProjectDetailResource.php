<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
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

        $totalTasks = (int) ($this->total_tasks ?? ($this->relationLoaded('tasks') ? $this->tasks->count() : 0));
        $pendingTasks = (int) ($this->pending_tasks ?? 0);
        $inProgressTasks = (int) ($this->in_progress_tasks ?? 0);
        $completedTasks = (int) ($this->completed_tasks ?? 0);

        if (! isset($this->total_tasks) && $this->relationLoaded('tasks')) {
            $pendingTasks = $this->tasks->where('status', TaskStatus::TODO)->count();
            $inProgressTasks = $this->tasks->whereIn('status', [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW])->count();
            $completedTasks = $this->tasks->where('status', TaskStatus::COMPLETED)->count();
        }

        $completionPercentage = $totalTasks > 0
            ? (int) round(($completedTasks / $totalTasks) * 100)
            : 0;

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
            'completion_percentage' => $completionPercentage,
            'timeline_progress_percentage' => $this->timelineProgressPercentage(),
            'total_tasks' => $totalTasks,
            'pending_tasks' => $pendingTasks,
            'in_progress_tasks' => $inProgressTasks,
            'completed_tasks' => $completedTasks,
            'total_tasks_count' => $totalTasks,
            'members' => $membersData,
        ];
    }
}
