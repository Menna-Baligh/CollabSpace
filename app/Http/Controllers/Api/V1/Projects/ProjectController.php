<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectDetailResource;
use App\Http\Resources\ProjectListResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectController extends Controller
{
    /**
     * List projects with filtering, sorting, and pagination.
     */
    public function index(Request $request, ProjectService $service): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:all,active,done,archived'],
            'sort' => ['nullable', 'string', 'in:newest,oldest,deadline,priority'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $paginator = $service->listProjects($validated, $perPage);

        return $this->successResponse(
            ProjectListResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Create a new project.
     */
    public function store(StoreProjectRequest $request, ProjectService $service): JsonResponse
    {
        try {
            $project = $service->createProject($request->user(), $request->validated());

            return $this->successResponse(
                new ProjectDetailResource($project),
                __('projects.created_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (Throwable $e) {
            Log::error('Failed to create project', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'operation' => 'project.store',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_create_project'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        $teamId = $request->query('team_id') ? (int) $request->query('team_id') : null;

        try {
            $members = $service->getProjectMembers($project, $teamId);

            return $this->successResponse(new ProjectDetailResource($project, $members));
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'team_not_in_project',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project, ProjectService $service): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            $updated = $service->updateProject($project, $request->validated());

            return $this->successResponse(
                new ProjectDetailResource($updated),
                __('projects.updated_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to update project', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'project.update',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_update_project'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Archive the specified project.
     */
    public function archive(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        $this->authorize('archive', $project);

        try {
            $archived = $service->archiveProject($project);

            return $this->successResponse(
                new ProjectDetailResource($archived),
                __('projects.archived_successfully')
            );
        } catch (Throwable $e) {
            Log::error('Failed to archive project', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'project.archive',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_archive_project'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Unarchive the specified project.
     */
    public function unarchive(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        $this->authorize('unarchive', $project);

        try {
            $unarchived = $service->unarchiveProject($project);

            return $this->successResponse(
                new ProjectDetailResource($unarchived),
                __('projects.unarchived_successfully')
            );
        } catch (Throwable $e) {
            Log::error('Failed to unarchive project', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'project.unarchive',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_unarchive_project'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Soft-delete the specified project.
     */
    public function destroy(Request $request, Project $project, ProjectService $service): JsonResponse
    {
        $this->authorize('delete', $project);

        try {
            $service->deleteProject($project);

            return $this->successResponse(
                null,
                __('projects.deleted_successfully')
            );
        } catch (Throwable $e) {
            Log::error('Failed to delete project', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'project.destroy',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_delete_project'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
