<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;
        $priorityValue = $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority;

        $reviewerData = null;
        if ($this->reviewer) {
            $reviewerData = [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'avatar' => null,
            ];
        }

        $assigneesData = [];
        if ($this->relationLoaded('assignees')) {
            $assigneesData = $this->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => null,
            ])->values()->all();
        }

        $teamData = null;
        if ($this->team) {
            $teamData = [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ];
        }

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $statusValue,
            'priority' => $priorityValue,
            'progress' => (int) $this->progress,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'position' => (int) $this->position,
            'comments_count' => 0,
            'reviewer' => $reviewerData,
            'assignees' => $assigneesData,
            'team' => $teamData,
        ];

        // Include project object when relation is loaded (for Global Tasks)
        if ($this->relationLoaded('project')) {
            $data['project'] = [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ];
        }

        return $data;
    }
}
