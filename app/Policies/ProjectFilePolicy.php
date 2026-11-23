<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    /**
     * Determine whether the user can view any files.
     */
    public function viewAny(User $user): bool
    {
        return ! $user->isSuspended();
    }

    /**
     * Determine whether the user can view or download the file.
     */
    public function view(User $user, ProjectFile $file): bool
    {
        return ! $user->isSuspended();
    }

    /**
     * Determine whether the user can upload files to the project.
     */
    public function upload(User $user, Project $project): bool
    {
        return ! $user->isSuspended();
    }

    /**
     * Determine whether the user can delete the file.
     * Authorized for: Project Manager of the project or the user who uploaded the file.
     */
    public function delete(User $user, ProjectFile $file): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $user->isProjectManagerOf($file->project_id)
            || (int) $file->uploaded_by === (int) $user->id;
    }
}
