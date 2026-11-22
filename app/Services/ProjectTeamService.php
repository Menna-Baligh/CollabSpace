<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectTeamService
{
    /**
     * List all teams belonging to a project.
     *
     * @return Collection<int, ProjectTeam>
     */
    public function listTeams(Project $project): Collection
    {
        return ProjectTeam::where('project_id', $project->id)
            ->with([
                'leader',
                'members' => fn ($q) => $q->select('users.id', 'users.name'),
            ])
            ->get();
    }

    /**
     * Create a team within a project atomically.
     *
     * @param  array{name: string, color?: ?string, leader_id?: ?int, leader_role_title?: ?string}  $data
     */
    public function createTeam(Project $project, array $data): ProjectTeam
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        return DB::transaction(function () use ($project, $data) {
            $leaderId = ! empty($data['leader_id']) ? (int) $data['leader_id'] : null;

            if ($leaderId !== null) {
                if (! $project->isMemberOfProject($leaderId)) {
                    throw new InvalidArgumentException(__('projects.team_leader_must_be_project_member'));
                }
            }

            $team = ProjectTeam::create([
                'project_id' => $project->id,
                'name' => $data['name'],
                'color' => $data['color'] ?? null,
                'leader_id' => $leaderId,
            ]);

            // If leader assigned, automatically add to team members with leader_role_title
            if ($leaderId !== null) {
                $team->members()->attach($leaderId, [
                    'role_title' => $data['leader_role_title'] ?? null,
                ]);
            }

            return $team->load(['leader', 'members']);
        });
    }

    /**
     * Update a team within a project.
     * Accepts $project explicitly to avoid lazy-loading $team->project.
     *
     * @param  array{name?: string, color?: ?string, leader_id?: ?int, leader_role_title?: ?string}  $data
     */
    public function updateTeam(Project $project, ProjectTeam $team, array $data): ProjectTeam
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        return DB::transaction(function () use ($project, $team, $data) {
            if (array_key_exists('leader_id', $data)) {
                $newLeaderId = ! empty($data['leader_id']) ? (int) $data['leader_id'] : null;

                if ($newLeaderId !== null) {
                    if (! $project->isMemberOfProject($newLeaderId)) {
                        throw new InvalidArgumentException(__('projects.team_leader_must_be_project_member'));
                    }

                    // Automatically add new leader to team members if not already
                    if (! $team->hasMember($newLeaderId)) {
                        $team->members()->attach($newLeaderId, [
                            'role_title' => $data['leader_role_title'] ?? null,
                        ]);
                    } elseif (! empty($data['leader_role_title'])) {
                        $team->members()->updateExistingPivot($newLeaderId, [
                            'role_title' => $data['leader_role_title'],
                        ]);
                    }

                    $team->leader_id = $newLeaderId;
                } else {
                    $team->leader_id = null;
                }
            }

            if (isset($data['name'])) {
                $team->name = $data['name'];
            }

            if (array_key_exists('color', $data)) {
                $team->color = $data['color'];
            }

            $team->save();

            return $team->fresh(['leader', 'members']);
        });
    }

    /**
     * Delete a team.
     * Accepts $project explicitly to avoid lazy-loading $team->project.
     */
    public function deleteTeam(Project $project, ProjectTeam $team): void
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        $team->delete();
    }

    /**
     * Add a member to a team.
     * Accepts $project explicitly to avoid lazy-loading $team->project.
     */
    public function addMember(Project $project, ProjectTeam $team, int $userId, ?string $roleTitle = null): User
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        if (! $project->isMemberOfProject($userId)) {
            throw new InvalidArgumentException(__('projects.team_member_must_be_project_member'));
        }

        if ($team->hasMember($userId)) {
            throw new InvalidArgumentException(__('projects.team_member_already_exists'));
        }

        $team->members()->attach($userId, [
            'role_title' => $roleTitle,
        ]);

        return $team->members()->where('users.id', $userId)->first();
    }

    /**
     * Remove a member from a team.
     * Accepts $project explicitly to avoid lazy-loading $team->project.
     * If the removed member is the team leader, clear leader_id.
     */
    public function removeMember(Project $project, ProjectTeam $team, int $userId): void
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        if (! $team->hasMember($userId)) {
            throw new InvalidArgumentException(__('projects.team_member_not_found'));
        }

        DB::transaction(function () use ($team, $userId) {
            if ((int) $team->leader_id === (int) $userId) {
                $team->update(['leader_id' => null]);
            }

            $team->members()->detach($userId);
        });
    }
}
