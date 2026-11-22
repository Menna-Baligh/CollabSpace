<?php

namespace App\Services;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectService
{
    /**
     * List projects with filtering, sorting, and pagination.
     *
     * @param  array{search?: ?string, status?: ?string, sort?: ?string}  $filters
     */
    public function listProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query()
            ->with([
                'members' => fn ($q) => $q->select('users.id', 'users.name')->limit(4),
            ])
            ->withCount('members');

        // Search by name
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        // Status filtering: 'all', 'active', 'done', 'archived'
        $status = strtolower($filters['status'] ?? 'all');
        if ($status !== 'all' && in_array($status, ProjectStatus::values(), true)) {
            $query->where('status', $status);
        }

        // Sorting
        $sort = strtolower($filters['sort'] ?? 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'deadline' => $query->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END, deadline ASC')->orderBy('id', 'desc'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")->orderBy('id', 'desc'),
            default => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
        };

        return $query->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Create a new project atomically and assign the creator as the first project manager.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProject(User $creator, array $data): Project
    {
        return DB::transaction(function () use ($creator, $data) {
            $project = Project::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'project_type' => $data['project_type'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'status' => $data['status'] ?? ProjectStatus::ACTIVE->value,
                'priority' => $data['priority'] ?? ProjectPriority::MEDIUM->value,
                'color' => $data['color'] ?? null,
                'icon' => $data['icon'] ?? null,
                'archived_at' => null,
            ]);

            $project->members()->attach($creator->id, [
                'role' => ProjectRole::PROJECT_MANAGER->value,
            ]);

            return $project->load([
                'members' => fn ($q) => $q->select('users.id', 'users.name'),
            ]);
        });
    }

    /**
     * Update an active project.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProject(Project $project, array $data): Project
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        $project->update($data);

        return $project->fresh([
            'members' => fn ($q) => $q->select('users.id', 'users.name'),
        ]);
    }

    /**
     * Archive a project idempotently, preserving the original archived_at timestamp.
     */
    public function archiveProject(Project $project): Project
    {
        if (! $project->isArchived()) {
            $project->update([
                'status' => ProjectStatus::ARCHIVED->value,
                'archived_at' => now(),
            ]);
        }

        return $project->fresh([
            'members' => fn ($q) => $q->select('users.id', 'users.name'),
        ]);
    }

    /**
     * Unarchive a project idempotently, restoring active status.
     */
    public function unarchiveProject(Project $project): Project
    {
        if ($project->isArchived()) {
            $project->update([
                'status' => ProjectStatus::ACTIVE->value,
                'archived_at' => null,
            ]);
        }

        return $project->fresh([
            'members' => fn ($q) => $q->select('users.id', 'users.name'),
        ]);
    }

    /**
     * Soft-delete a project.
     */
    public function deleteProject(Project $project): bool
    {
        return (bool) $project->delete();
    }

    /**
     * Get processed project members with leader status and project role, optionally filtered by team.
     *
     * @return Collection<int, ProjectMemberResource>
     */
    public function getProjectMembers(Project $project, ?int $teamId = null): Collection
    {
        if ($teamId) {
            /** @var ProjectTeam|null $team */
            $team = $project->teams()->where('id', $teamId)->first();

            if (! $team) {
                throw new InvalidArgumentException(__('projects.team_not_in_project'));
            }

            $teamMembers = $team->members()
                ->select('users.id', 'users.name')
                ->get();

            // Load project membership roles for these users in one batch query to prevent N+1
            $projectRoles = $project->members()
                ->whereIn('users.id', $teamMembers->pluck('id'))
                ->get()
                ->keyBy('id');

            return $teamMembers->map(function ($user) use ($team, $projectRoles) {
                $isLeader = (int) $team->leader_id === (int) $user->id;
                $user->setAttribute('is_leader', $isLeader);
                $user->setAttribute('role', $projectRoles->get($user->id)?->pivot?->role ?? 'member');

                return new ProjectMemberResource($user, $isLeader);
            });
        }

        // All project members
        $members = $project->members()
            ->select('users.id', 'users.name')
            ->get();

        $leaderUserIds = $project->teams()
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->all();

        return $members->map(function ($user) use ($leaderUserIds) {
            $isLeader = in_array($user->id, $leaderUserIds, true);
            $user->setAttribute('is_leader', $isLeader);

            return new ProjectMemberResource($user, $isLeader);
        });
    }
}
