<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserSuspensionService
{
    /**
     * Find all projects where the given user is the sole active Project Manager.
     *
     * @return Collection<int, Project>
     */
    public function getProjectsWhereSoleProjectManager(User $user): Collection
    {
        // Get all projects where the user is a PM
        $pmProjectIds = DB::table('project_user')
            ->where('user_id', $user->id)
            ->where('role', ProjectRole::PROJECT_MANAGER->value)
            ->pluck('project_id');

        if ($pmProjectIds->isEmpty()) {
            return collect();
        }

        // For each project, check if there are other active PMs
        $soleProjectIds = [];
        foreach ($pmProjectIds as $projectId) {
            $otherActivePMCount = DB::table('project_user')
                ->join('users', 'project_user.user_id', '=', 'users.id')
                ->where('project_user.project_id', $projectId)
                ->where('project_user.role', ProjectRole::PROJECT_MANAGER->value)
                ->where('project_user.user_id', '!=', $user->id)
                ->whereNull('users.suspended_at')
                ->count();

            if ($otherActivePMCount === 0) {
                $soleProjectIds[] = $projectId;
            }
        }

        if (empty($soleProjectIds)) {
            return collect();
        }

        return Project::whereIn('id', $soleProjectIds)->get();
    }

    /**
     * Suspend a user with atomic project handover if required.
     *
     * @param  list<array{project_id: int, replacement_user_id: int}>|null  $handovers
     * @return array{success: bool, handover_required?: bool, projects?: list<array{id: int, name: string}>}
     */
    public function suspend(User $targetUser, ?array $handovers = null): array
    {
        if ($targetUser->isSuspended()) {
            // Already suspended; ensure tokens are revoked
            $targetUser->tokens()->delete();

            return ['success' => true];
        }

        $soleProjects = $this->getProjectsWhereSoleProjectManager($targetUser);

        // Case A: User is not sole PM in any project
        if ($soleProjects->isEmpty()) {
            $targetUser->update(['suspended_at' => now()]);
            $targetUser->tokens()->delete();

            return ['success' => true];
        }

        // Case B: User is sole PM in 1+ projects, but handovers not supplied
        if (empty($handovers)) {
            return [
                'success' => false,
                'handover_required' => true,
                'projects' => $soleProjects->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->values()->all(),
            ];
        }

        // Case C: Handovers supplied — validate and execute atomically
        $requiredProjectIds = $soleProjects->pluck('id')->sort()->values()->all();
        $submittedProjectIds = collect($handovers)->pluck('project_id')->sort()->values()->all();

        // 1. Check for duplicates
        if (count($submittedProjectIds) !== count(array_unique($submittedProjectIds))) {
            throw new InvalidArgumentException(__('projects.duplicate_handover_project'));
        }

        // 2. Validate exact match of required projects
        if ($requiredProjectIds !== $submittedProjectIds) {
            throw new InvalidArgumentException(__('projects.replacement_project_mismatch'));
        }

        return DB::transaction(function () use ($targetUser, $handovers) {
            // Re-lock target user
            $targetUser = User::where('id', $targetUser->id)->lockForUpdate()->firstOrFail();

            foreach ($handovers as $handover) {
                $projectId = (int) $handover['project_id'];
                $replacementUserId = (int) $handover['replacement_user_id'];

                if ($replacementUserId === (int) $targetUser->id) {
                    throw new InvalidArgumentException(__('projects.replacement_cannot_be_self'));
                }

                $replacementUser = User::where('id', $replacementUserId)->lockForUpdate()->first();

                if (! $replacementUser) {
                    throw new InvalidArgumentException(__('projects.invalid_replacement_user'));
                }

                if ($replacementUser->isSuspended()) {
                    throw new InvalidArgumentException(__('projects.replacement_is_suspended'));
                }

                $project = Project::where('id', $projectId)->lockForUpdate()->firstOrFail();

                // If replacement is already a member of the project, promote them
                $existingMembership = DB::table('project_user')
                    ->where('project_id', $projectId)
                    ->where('user_id', $replacementUserId)
                    ->lockForUpdate()
                    ->first();

                if ($existingMembership) {
                    DB::table('project_user')
                        ->where('project_id', $projectId)
                        ->where('user_id', $replacementUserId)
                        ->update([
                            'role' => ProjectRole::PROJECT_MANAGER->value,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('project_user')->insert([
                        'project_id' => $projectId,
                        'user_id' => $replacementUserId,
                        'role' => ProjectRole::PROJECT_MANAGER->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Demote old sole PM to member
                DB::table('project_user')
                    ->where('project_id', $projectId)
                    ->where('user_id', $targetUser->id)
                    ->update([
                        'role' => ProjectRole::MEMBER->value,
                        'updated_at' => now(),
                    ]);
            }

            // Finally, suspend target user and revoke all tokens
            $targetUser->update(['suspended_at' => now()]);
            $targetUser->tokens()->delete();

            return ['success' => true];
        });
    }
}
