<?php

return [
    // Projects
    'created_successfully' => 'Project created successfully.',
    'updated_successfully' => 'Project updated successfully.',
    'archived_successfully' => 'Project archived successfully.',
    'unarchived_successfully' => 'Project unarchived successfully.',
    'deleted_successfully' => 'Project deleted successfully.',
    'not_found' => 'Project not found.',
    'forbidden' => 'You do not have permission to manage this project.',
    'archived_modification_prohibited' => 'Cannot modify an archived project.',
    'invalid_dates' => 'The deadline must be a date after or equal to the start date.',
    'status_archived_not_allowed' => 'Project cannot be set to archived status via update. Use the archive endpoint.',

    // Members
    'member_added_successfully' => 'Project member added successfully.',
    'member_role_updated_successfully' => 'Project member role updated successfully.',
    'member_removed_successfully' => 'Project member removed successfully.',
    'member_already_exists' => 'User is already a member of this project.',
    'user_not_project_member' => 'User is not a member of this project.',
    'cannot_add_suspended_user' => 'Cannot add a suspended user to a project.',
    'cannot_remove_last_project_manager' => 'Cannot remove the last project manager.',
    'cannot_demote_last_project_manager' => 'Cannot remove the last project manager.',

    // Teams
    'team_created_successfully' => 'Team created successfully.',
    'team_updated_successfully' => 'Team updated successfully.',
    'team_deleted_successfully' => 'Team deleted successfully.',
    'team_not_in_project' => 'Team does not belong to this project.',
    'team_name_already_exists' => 'A team with this name already exists in this project.',
    'team_leader_must_be_project_member' => 'The team leader must already be a member of the project.',
    'team_member_must_be_project_member' => 'The user must be a member of the project before joining a team.',
    'team_member_added_successfully' => 'Team member added successfully.',
    'team_member_removed_successfully' => 'Team member removed successfully.',
    'team_member_already_exists' => 'User is already a member of this team.',
    'team_member_not_found' => 'User is not a member of this team.',

    // Handover & Suspension
    'user_suspended_successfully' => 'User suspended successfully.',
    'handover_required' => 'Handover required: user is the sole active project manager in one or more projects.',
    'handover_completed_successfully' => 'Handover completed and user suspended successfully.',
    'invalid_replacement_user' => 'Invalid replacement user specified.',
    'replacement_cannot_be_self' => 'Replacement user cannot be the user being suspended.',
    'replacement_is_suspended' => 'Replacement user is suspended and cannot be assigned as project manager.',
    'replacement_project_mismatch' => 'Handover project list does not match the required projects.',
    'duplicate_handover_project' => 'Duplicate handover project specified.',
    'admin_suspension_only' => 'Only system administrators can suspend user accounts.',

    // Unexpected 500 Errors
    'unexpected_error_create_project' => 'An unexpected error occurred while creating the project.',
    'unexpected_error_update_project' => 'An unexpected error occurred while updating the project.',
    'unexpected_error_archive_project' => 'An unexpected error occurred while archiving the project.',
    'unexpected_error_unarchive_project' => 'An unexpected error occurred while unarchiving the project.',
    'unexpected_error_delete_project' => 'An unexpected error occurred while deleting the project.',
    'unexpected_error_add_member' => 'An unexpected error occurred while adding the project member.',
    'unexpected_error_update_member' => 'An unexpected error occurred while updating the member role.',
    'unexpected_error_remove_member' => 'An unexpected error occurred while removing the project member.',
    'unexpected_error_create_team' => 'An unexpected error occurred while creating the team.',
    'unexpected_error_update_team' => 'An unexpected error occurred while updating the team.',
    'unexpected_error_delete_team' => 'An unexpected error occurred while deleting the team.',
    'unexpected_error_add_team_member' => 'An unexpected error occurred while adding the team member.',
    'unexpected_error_remove_team_member' => 'An unexpected error occurred while removing the team member.',
    'unexpected_error_suspend_user' => 'An unexpected error occurred while suspending the user.',

    // Slice 3 Tasks
    'task_created_successfully' => 'Task created successfully.',
    'task_updated_successfully' => 'Task updated successfully.',
    'task_status_updated_successfully' => 'Task status updated successfully.',
    'task_deleted_successfully' => 'Task deleted successfully.',
    'task_not_found' => 'Task not found.',
    'task_not_in_project' => 'Task does not belong to this project.',
    'unexpected_error_create_task' => 'An unexpected error occurred while creating the task.',
    'unexpected_error_update_task' => 'An unexpected error occurred while updating the task.',
    'unexpected_error_delete_task' => 'An unexpected error occurred while deleting the task.',

    // Slice 3 Files
    'file_uploaded_successfully' => 'File uploaded successfully.',
    'file_deleted_successfully' => 'File deleted successfully.',
    'file_not_found' => 'File not found.',
    'file_not_in_project' => 'File does not belong to this project.',
    'file_not_found_on_disk' => 'The requested file could not be found on storage.',
    'unexpected_error_upload_file' => 'An unexpected error occurred while uploading the file.',
    'unexpected_error_download_file' => 'An unexpected error occurred while downloading the file.',
    'unexpected_error_delete_file' => 'An unexpected error occurred while deleting the file.',
];
