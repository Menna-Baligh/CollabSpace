<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class TeamMemberResource extends JsonResource
{
    /**
     * Optional is_leader flag override.
     */
    protected ?bool $isLeaderOverride;

    /**
     * Optional assigned tasks count override.
     */
    protected ?int $assignedTasksCountOverride;

    /**
     * Optional completed tasks count override.
     */
    protected ?int $completedTasksCountOverride;

    public function __construct(
        $resource,
        ?bool $isLeader = null,
        ?int $assignedTasksCount = null,
        ?int $completedTasksCount = null
    ) {
        parent::__construct($resource);
        $this->isLeaderOverride = $isLeader;
        $this->assignedTasksCountOverride = $assignedTasksCount;
        $this->completedTasksCountOverride = $completedTasksCount;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleTitle = $this->pivot?->role_title;
        $isLeader = $this->isLeaderOverride ?? (bool) ($this->is_leader ?? false);

        $assignedTasksCount = $this->assignedTasksCountOverride
            ?? (int) ($this->assigned_tasks_count ?? 0);

        $completedTasksCount = $this->completedTasksCountOverride
            ?? (int) ($this->completed_tasks_count ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => null,
            'role_title' => $roleTitle,
            'is_leader' => $isLeader,
            'assigned_tasks_count' => $assignedTasksCount,
            'completed_tasks_count' => $completedTasksCount,
        ];
    }
}
