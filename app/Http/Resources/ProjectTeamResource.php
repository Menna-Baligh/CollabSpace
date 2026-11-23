<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\ProjectTeam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectTeam
 */
class ProjectTeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $leaderData = null;

        if ($this->leader) {
            // Find leader's role_title from team members pivot if loaded
            $leaderMember = $this->relationLoaded('members')
                ? $this->members->firstWhere('id', $this->leader->id)
                : null;

            $roleTitle = $leaderMember?->pivot?->role_title;

            $leaderData = [
                'id' => $this->leader->id,
                'name' => $this->leader->name,
                'avatar' => null,
                'role_title' => $roleTitle,
            ];
        }

        $membersData = [];

        if ($this->relationLoaded('members')) {
            $membersData = $this->members->map(function ($member) {
                $isLeader = (int) $this->leader_id === (int) $member->id;

                return new TeamMemberResource(
                    $member,
                    $isLeader,
                    $member->assigned_tasks_count ?? null,
                    $member->completed_tasks_count ?? null
                );
            });
        }

        $totalTeamTasks = (int) ($this->tasks_count ?? 0);
        $completedTeamTasks = (int) ($this->completed_tasks_count ?? 0);

        if (! isset($this->tasks_count) && $this->relationLoaded('tasks')) {
            $totalTeamTasks = $this->tasks->count();
            $completedTeamTasks = $this->tasks->where('status', TaskStatus::COMPLETED)->count();
        }

        $completionPercentage = $totalTeamTasks > 0
            ? (int) round(($completedTeamTasks / $totalTeamTasks) * 100)
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'completion_percentage' => $completionPercentage,
            'leader' => $leaderData,
            'members' => $membersData,
        ];
    }
}
