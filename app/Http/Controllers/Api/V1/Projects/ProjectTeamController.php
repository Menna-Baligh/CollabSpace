<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectTeamRequest;
use App\Http\Requests\Projects\UpdateProjectTeamRequest;
use App\Http\Resources\ProjectTeamResource;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Services\ProjectTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectTeamController extends Controller
{
    /**
     * List all teams belonging to the project.
     */
    public function index(Request $request, Project $project, ProjectTeamService $service): JsonResponse
    {
        $teams = $service->listTeams($project);

        return $this->successResponse(ProjectTeamResource::collection($teams));
    }

    /**
     * Create a team in the project.
     */
    public function store(StoreProjectTeamRequest $request, Project $project, ProjectTeamService $service): JsonResponse
    {
        $this->authorize('manageTeams', $project);

        try {
            $team = $service->createTeam($project, $request->validated());

            return $this->successResponse(
                new ProjectTeamResource($team),
                __('projects.team_created_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to create project team', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'project_team.store',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_create_team'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update a team in the project.
     */
    public function update(
        UpdateProjectTeamRequest $request,
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
            $updated = $service->updateTeam($project, $team, $request->validated());

            return $this->successResponse(
                new ProjectTeamResource($updated),
                __('projects.team_updated_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to update project team', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'team_id' => $team->id,
                'operation' => 'project_team.update',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_update_team'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a team.
     */
    public function destroy(
        Request $request,
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
            $service->deleteTeam($project, $team);

            return $this->successResponse(
                null,
                __('projects.team_deleted_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to delete project team', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'team_id' => $team->id,
                'operation' => 'project_team.destroy',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_delete_team'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
