<?php

namespace App\Http\Resources;

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

                return new TeamMemberResource($member, $isLeader);
            });
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'completion_percentage' => 0,
            'leader' => $leaderData,
            'members' => $membersData,
        ];
    }
}
