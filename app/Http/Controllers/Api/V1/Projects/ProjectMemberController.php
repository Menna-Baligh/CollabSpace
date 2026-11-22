<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\AddProjectMemberRequest;
use App\Http\Requests\Projects\UpdateProjectMemberRoleRequest;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectMemberController extends Controller
{
    /**
     * Add a member to the project.
     */
    public function store(AddProjectMemberRequest $request, Project $project, ProjectMemberService $service): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        try {
            $member = $service->addMember(
                $project,
                (int) $request->validated('user_id'),
                $request->validated('role')
            );

            return $this->successResponse(
                new ProjectMemberResource($member),
                __('projects.member_added_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to add project member', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'target_user_id' => $request->validated('user_id'),
                'operation' => 'project_member.store',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_add_member'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update a project member's role (promote/demote).
     */
    public function update(
        UpdateProjectMemberRoleRequest $request,
        Project $project,
        User $user,
        ProjectMemberService $service
    ): JsonResponse {
        $this->authorize('manageMembers', $project);

        try {
            $member = $service->updateMemberRole(
                $project,
                (int) $user->id,
                $request->validated('role')
            );

            return $this->successResponse(
                new ProjectMemberResource($member),
                __('projects.member_role_updated_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to update project member role', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'target_user_id' => $user->id,
                'operation' => 'project_member.update',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_update_member'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Remove a member from the project.
     */
    public function destroy(
        Request $request,
        Project $project,
        User $user,
        ProjectMemberService $service
    ): JsonResponse {
        $this->authorize('manageMembers', $project);

        try {
            $service->removeMember($project, (int) $user->id);

            return $this->successResponse(
                null,
                __('projects.member_removed_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to remove project member', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'target_user_id' => $user->id,
                'operation' => 'project_member.destroy',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_remove_member'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
