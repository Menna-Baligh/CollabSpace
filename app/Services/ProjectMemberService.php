<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectMemberService
{
    /**
     * Add a member to the project.
     */
    public function addMember(Project $project, int $userId, string $role): User
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        $user = User::find($userId);

        if (! $user) {
            throw new InvalidArgumentException(__('projects.not_found'));
        }

        if ($user->isSuspended()) {
            throw new InvalidArgumentException(__('projects.cannot_add_suspended_user'));
        }

        if ($project->isMemberOfProject($user)) {
            throw new InvalidArgumentException(__('projects.member_already_exists'));
        }

        $project->members()->attach($user->id, [
            'role' => $role,
        ]);

        return $project->members()->where('users.id', $user->id)->first();
    }

    /**
     * Update a project member's role (promote/demote) with Last Project Manager protection.
     */
    public function updateMemberRole(Project $project, int $userId, string $newRole): User
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        return DB::transaction(function () use ($project, $userId, $newRole) {
            // Check if user is a member
            $membership = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                throw new InvalidArgumentException(__('projects.user_not_project_member'));
            }

            // If demoting from project_manager to member, enforce Last PM Protection
            if ($membership->role === ProjectRole::PROJECT_MANAGER->value && $newRole === ProjectRole::MEMBER->value) {
                $remainingActivePMs = DB::table('project_user')
                    ->join('users', 'project_user.user_id', '=', 'users.id')
                    ->where('project_user.project_id', $project->id)
                    ->where('project_user.user_id', '!=', $userId)
                    ->where('project_user.role', ProjectRole::PROJECT_MANAGER->value)
                    ->whereNull('users.suspended_at')
                    ->lockForUpdate()
                    ->count();

                if ($remainingActivePMs < 1) {
                    throw new InvalidArgumentException(__('projects.cannot_demote_last_project_manager'));
                }
            }

            DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->update([
                    'role' => $newRole,
                    'updated_at' => now(),
                ]);

            return $project->members()->where('users.id', $userId)->first();
        });
    }

    /**
     * Remove a member from the project.
     * Enforces Last Project Manager protection, removes from teams, and clears leader assignments.
     */
    public function removeMember(Project $project, int $userId): void
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        DB::transaction(function () use ($project, $userId) {
            $membership = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $membership) {
                throw new InvalidArgumentException(__('projects.user_not_project_member'));
            }

            // Last PM protection
            if ($membership->role === ProjectRole::PROJECT_MANAGER->value) {
                $remainingActivePMs = DB::table('project_user')
                    ->join('users', 'project_user.user_id', '=', 'users.id')
                    ->where('project_user.project_id', $project->id)
                    ->where('project_user.user_id', '!=', $userId)
                    ->where('project_user.role', ProjectRole::PROJECT_MANAGER->value)
                    ->whereNull('users.suspended_at')
                    ->lockForUpdate()
                    ->count();

                if ($remainingActivePMs < 1) {
                    throw new InvalidArgumentException(__('projects.cannot_remove_last_project_manager'));
                }
            }

            // 1. Set leader_id to null for any teams in this project led by this user
            ProjectTeam::where('project_id', $project->id)
                ->where('leader_id', $userId)
                ->update(['leader_id' => null]);

            // 2. Remove user from all teams belonging to this project
            $teamIds = ProjectTeam::where('project_id', $project->id)->pluck('id');
            if ($teamIds->isNotEmpty()) {
                DB::table('project_team_user')
                    ->whereIn('project_team_id', $teamIds)
                    ->where('user_id', $userId)
                    ->delete();
            }

            // 3. Remove project membership
            DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->delete();
        });
    }
}
