<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return ! $user->isSuspended();
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return ! $user->isSuspended();
    }

    /**
     * Determine whether the user can create tasks in the project.
     */
    public function create(User $user, Project $project): bool
    {
        return ! $user->isSuspended() && $project->hasMember($user);
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $user->isProjectManagerOf($task->project_id)
            || (int) $task->created_by === (int) $user->id
            || (int) $task->reviewer_id === (int) $user->id
            || $task->assignees()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update the status/position of the task (Kanban drag and drop).
     */
    public function updateStatus(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Determine whether the user can delete the task.
     * Authorized for: Project Manager of the project or the task creator.
     */
    public function delete(User $user, Task $task): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $user->isProjectManagerOf($task->project_id)
            || (int) $task->created_by === (int) $user->id;
    }
}
