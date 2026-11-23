<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskService
{
    /**
     * Get tasks for a project grouped by Kanban column.
     *
     * @param  array{search?: ?string, status?: ?string, priority?: ?string, team_id?: ?int, assignee_id?: ?int}  $filters
     * @return array{
     *     columns: array<string, Collection<int, Task>>,
     *     counts: array<string, int>
     * }
     */
    public function getProjectKanbanTasks(Project $project, array $filters = []): array
    {
        $query = Task::where('project_id', $project->id)
            ->with([
                'reviewer:id,name',
                'assignees:id,name',
                'team:id,name',
            ]);

        $this->applyFilters($query, $filters);

        // Order by position ASC, then id ASC deterministically
        $tasks = $query->orderBy('position', 'asc')->orderBy('id', 'asc')->get();

        return $this->groupIntoKanban($tasks);
    }

    /**
     * Get global tasks across projects visible to the user grouped by Kanban column.
     *
     * @param  array{search?: ?string, project_id?: ?int, scope?: ?string, status?: ?string, priority?: ?string}  $filters
     * @return array{
     *     columns: array<string, Collection<int, Task>>,
     *     counts: array<string, int>
     * }
     */
    public function getGlobalKanbanTasks(User $user, array $filters = []): array
    {
        $query = Task::query()
            ->whereHas('project', function ($q) {
                // Exclude soft-deleted projects automatically handled by Eloquent SoftDeletes
            })
            ->with([
                'project:id,name',
                'reviewer:id,name',
                'assignees:id,name',
                'team:id,name',
            ]);

        // Filter by specific project if requested
        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        // Scope: my_tasks (assigned or reviewer) vs all
        $scope = strtolower($filters['scope'] ?? 'all');
        if ($scope === 'my_tasks') {
            $query->where(function (Builder $q) use ($user) {
                $q->where('reviewer_id', $user->id)
                    ->orWhereHas('assignees', function (Builder $sq) use ($user) {
                        $sq->where('users.id', $user->id);
                    });
            });
        }

        $this->applyFilters($query, $filters);

        $tasks = $query->orderBy('position', 'asc')->orderBy('id', 'asc')->get();

        return $this->groupIntoKanban($tasks);
    }

    /**
     * Create a task atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(Project $project, User $creator, array $data): Task
    {
        if ($project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        $this->validateRelationsInProject($project, $data);

        return DB::transaction(function () use ($project, $creator, $data) {
            $status = $data['status'] ?? TaskStatus::TODO->value;
            $progress = (int) ($data['progress'] ?? 0);

            // Rule: When creating a task with status completed, set progress to 100
            if ($status === TaskStatus::COMPLETED->value) {
                $progress = 100;
            }

            // Assign initial position at the end of the status column
            $maxPosition = Task::where('project_id', $project->id)
                ->where('status', $status)
                ->max('position');
            $position = $maxPosition !== null ? $maxPosition + 1 : 0;

            $task = Task::create([
                'project_id' => $project->id,
                'project_team_id' => $data['project_team_id'] ?? null,
                'created_by' => $creator->id,
                'reviewer_id' => $data['reviewer_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'priority' => $data['priority'] ?? TaskPriority::MEDIUM->value,
                'progress' => $progress,
                'due_date' => $data['due_date'] ?? null,
                'position' => $position,
            ]);

            if (! empty($data['assignee_ids'])) {
                $uniqueAssignees = array_values(array_unique($data['assignee_ids']));
                $task->assignees()->sync($uniqueAssignees);
            }

            return $task->load([
                'project:id,name',
                'reviewer:id,name',
                'assignees:id,name',
                'team:id,name',
            ]);
        });
    }

    /**
     * Update a task.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTask(Task $task, array $data): Task
    {
        if ($task->project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        $this->validateRelationsInProject($task->project, $data);

        return DB::transaction(function () use ($task, $data) {
            $updateAttributes = [];

            if (array_key_exists('title', $data)) {
                $updateAttributes['title'] = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $updateAttributes['description'] = $data['description'];
            }
            if (array_key_exists('priority', $data)) {
                $updateAttributes['priority'] = $data['priority'];
            }
            if (array_key_exists('due_date', $data)) {
                $updateAttributes['due_date'] = $data['due_date'];
            }
            if (array_key_exists('project_team_id', $data)) {
                $updateAttributes['project_team_id'] = $data['project_team_id'];
            }
            if (array_key_exists('reviewer_id', $data)) {
                $updateAttributes['reviewer_id'] = $data['reviewer_id'];
            }

            $currentStatus = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;
            $newStatus = $data['status'] ?? $currentStatus;

            if (array_key_exists('status', $data)) {
                $updateAttributes['status'] = $newStatus;
            }

            // Progress handling
            if (array_key_exists('progress', $data)) {
                $updateAttributes['progress'] = (int) $data['progress'];
            }

            // When a task's status becomes completed, automatically set progress = 100
            if ($newStatus === TaskStatus::COMPLETED->value) {
                $updateAttributes['progress'] = 100;
            }

            if (! empty($updateAttributes)) {
                $task->update($updateAttributes);
            }

            // Sync assignees safely if assignee_ids is provided
            if (array_key_exists('assignee_ids', $data)) {
                $assigneeIds = is_array($data['assignee_ids'])
                    ? array_values(array_unique($data['assignee_ids']))
                    : [];
                $task->assignees()->sync($assigneeIds);
            }

            return $task->fresh([
                'project:id,name',
                'reviewer:id,name',
                'assignees:id,name',
                'team:id,name',
            ]);
        });
    }

    /**
     * Update task status and position (Kanban drag and drop).
     *
     * @param  array{status: string, progress?: ?int, position?: ?int}  $data
     */
    public function updateTaskStatus(Task $task, array $data): Task
    {
        if ($task->project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        return DB::transaction(function () use ($task, $data) {
            $newStatus = $data['status'];
            $updateAttributes = ['status' => $newStatus];

            if ($newStatus === TaskStatus::COMPLETED->value) {
                $updateAttributes['progress'] = 100;
            } elseif (array_key_exists('progress', $data) && $data['progress'] !== null) {
                $updateAttributes['progress'] = (int) $data['progress'];
            }

            if (array_key_exists('position', $data) && $data['position'] !== null) {
                $updateAttributes['position'] = (int) $data['position'];
            }

            $task->update($updateAttributes);

            return $task->fresh([
                'project:id,name',
                'reviewer:id,name',
                'assignees:id,name',
                'team:id,name',
            ]);
        });
    }

    /**
     * Delete a task.
     */
    public function deleteTask(Task $task): bool
    {
        if ($task->project->isArchived()) {
            throw new InvalidArgumentException(__('projects.archived_modification_prohibited'));
        }

        return (bool) $task->delete();
    }

    /**
     * Group a collection of tasks into the 4 Kanban columns with counts.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array{
     *     columns: array<string, Collection<int, Task>>,
     *     counts: array<string, int>
     * }
     */
    protected function groupIntoKanban(Collection $tasks): array
    {
        $columns = [
            TaskStatus::TODO->value => new Collection,
            TaskStatus::IN_PROGRESS->value => new Collection,
            TaskStatus::IN_REVIEW->value => new Collection,
            TaskStatus::COMPLETED->value => new Collection,
        ];

        foreach ($tasks as $task) {
            $statusKey = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;
            if (isset($columns[$statusKey])) {
                $columns[$statusKey]->push($task);
            }
        }

        $counts = [
            TaskStatus::TODO->value => $columns[TaskStatus::TODO->value]->count(),
            TaskStatus::IN_PROGRESS->value => $columns[TaskStatus::IN_PROGRESS->value]->count(),
            TaskStatus::IN_REVIEW->value => $columns[TaskStatus::IN_REVIEW->value]->count(),
            TaskStatus::COMPLETED->value => $columns[TaskStatus::COMPLETED->value]->count(),
        ];

        return [
            'columns' => $columns,
            'counts' => $counts,
        ];
    }

    /**
     * Apply common filters (search, status, priority, team, assignee).
     *
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('title', 'like', "%{$search}%");
        }

        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', $filters['status']);
            $statuses = array_filter(array_map('trim', $statuses));
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if (! empty($filters['priority'])) {
            $priorities = is_array($filters['priority'])
                ? $filters['priority']
                : explode(',', $filters['priority']);
            $priorities = array_filter(array_map('trim', $priorities));
            if (! empty($priorities)) {
                $query->whereIn('priority', $priorities);
            }
        }

        if (! empty($filters['team_id'])) {
            $query->where('project_team_id', (int) $filters['team_id']);
        }

        if (! empty($filters['assignee_id'])) {
            $assigneeId = (int) $filters['assignee_id'];
            $query->whereHas('assignees', function (Builder $q) use ($assigneeId) {
                $q->where('users.id', $assigneeId);
            });
        }
    }

    /**
     * Validate that team, reviewer, and assignees belong to the project.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateRelationsInProject(Project $project, array $data): void
    {
        if (! empty($data['project_team_id'])) {
            $teamExists = ProjectTeam::where('id', $data['project_team_id'])
                ->where('project_id', $project->id)
                ->exists();
            if (! $teamExists) {
                throw new InvalidArgumentException(__('projects.team_not_in_project'));
            }
        }

        if (! empty($data['reviewer_id'])) {
            if (! $project->hasMember((int) $data['reviewer_id'])) {
                throw new InvalidArgumentException(__('projects.user_not_project_member'));
            }
        }

        if (! empty($data['assignee_ids'])) {
            foreach ($data['assignee_ids'] as $userId) {
                if (! $project->hasMember((int) $userId)) {
                    throw new InvalidArgumentException(__('projects.user_not_project_member'));
                }
            }
        }
    }
}
