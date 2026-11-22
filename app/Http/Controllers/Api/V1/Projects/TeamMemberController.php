<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\AddTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use App\Services\ProjectTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TeamMemberController extends Controller
{
    /**
     * Add a member to the team.
     */
    public function store(
        AddTeamMemberRequest $request,
        Project $project,
        ProjectTeam $team,
        ProjectTeamService $service
    ): JsonResponse {
        $this->authorize('manageTeams', $project);

        if ((int) $team->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.team_not_in_project'),
                'team_not_in_project',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $member = $service->addMember(
                $project,
                $team,
                (int) $request->validated('user_id'),
                $request->validated('role_title')
            );

            return $this->successResponse(
                new TeamMemberResource($member),
                __('projects.team_member_added_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to add team member', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'team_id' => $team->id,
                'target_user_id' => $request->validated('user_id'),
                'operation' => 'team_member.store',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_add_team_member'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Remove a member from the team.
     */
    public function destroy(
        Request $request,
        Project $project,
        ProjectTeam $team,
        User $user,
        ProjectTeamService $service
    ): JsonResponse {
        $this->authorize('manageTeams', $project);

        if ((int) $team->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.team_not_in_project'),
                'team_not_in_project',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $service->removeMember($project, $team, (int) $user->id);

            return $this->successResponse(
                null,
                __('projects.team_member_removed_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to remove team member', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'team_id' => $team->id,
                'target_user_id' => $user->id,
                'operation' => 'team_member.destroy',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_remove_team_member'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
