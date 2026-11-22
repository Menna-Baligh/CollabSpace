<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->isProjectManagerOf($project);
    }

    /**
     * Determine whether the user can archive the project.
     */
    public function archive(User $user, Project $project): bool
    {
        return $user->isProjectManagerOf($project);
    }

    /**
     * Determine whether the user can unarchive the project.
     */
    public function unarchive(User $user, Project $project): bool
    {
        return $user->isProjectManagerOf($project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->isProjectManagerOf($project);
    }

    /**
     * Determine whether the user can manage project members.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->isProjectManagerOf($project);
    }

    /**
     * Determine whether the user can manage teams in the project.
     */
    public function manageTeams(User $user, Project $project): bool
    {
        return $user->isProjectManagerOf($project);
    }
}
