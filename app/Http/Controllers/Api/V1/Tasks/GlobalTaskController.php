<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GlobalTaskController extends Controller
{
    use ApiResponse;

    /**
     * List tasks across all projects grouped by Kanban column.
     */
    public function index(Request $request, TaskService $service): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'project_id' => $request->query('project_id'),
            'scope' => $request->query('scope', 'all'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ];

        $kanban = $service->getGlobalKanbanTasks($request->user(), $filters);

        $data = [
            'columns' => [
                'todo' => TaskResource::collection($kanban['columns']['todo']),
                'in_progress' => TaskResource::collection($kanban['columns']['in_progress']),
                'in_review' => TaskResource::collection($kanban['columns']['in_review']),
                'completed' => TaskResource::collection($kanban['columns']['completed']),
            ],
            'counts' => $kanban['counts'],
        ];

        return $this->successResponse($data);
    }

    /**
     * Create a task from the global tasks screen.
     */
    public function store(StoreTaskRequest $request, TaskService $service): JsonResponse
    {
        $project = Project::findOrFail($request->validated('project_id'));

        $this->authorize('create', [Task::class, $project]);

        try {
            $task = $service->createTask($project, $request->user(), $request->validated());

            return $this->successResponse(
                new TaskResource($task),
                __('projects.task_created_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to create global task', [
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'global_tasks.store',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_create_task'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
