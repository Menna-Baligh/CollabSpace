<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Enums\TaskStatus;
use App\Enums\UserActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use App\Services\UserActivityService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TaskController extends Controller
{
    use ApiResponse;

    /**
     * List tasks for a project grouped by Kanban column.
     */
    public function index(Request $request, Project $project, TaskService $service): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'team_id' => $request->query('team_id'),
            'assignee_id' => $request->query('assignee_id'),
        ];

        $kanban = $service->getProjectKanbanTasks($project, $filters);

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
     * Create a task within a project.
     */
    public function store(StoreTaskRequest $request, Project $project, TaskService $service): JsonResponse
    {
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
            Log::error('Failed to create task', [
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'tasks.store',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_create_task'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Display a specific task.
     */
    public function show(Project $project, Task $task): JsonResponse
    {
        if ((int) $task->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.task_not_in_project'),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $task->load([
            'reviewer:id,name',
            'assignees:id,name',
            'team:id,name',
            'creator:id,name',
        ]);

        return $this->successResponse(new TaskResource($task));
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task, TaskService $service, UserActivityService $activityService): JsonResponse
    {
        if ((int) $task->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.task_not_in_project'),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $this->authorize('update', $task);

        try {
            $oldStatus = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;
            $updated = $service->updateTask($task, $request->validated());
            $newStatus = $updated->status instanceof \BackedEnum ? $updated->status->value : $updated->status;

            if ($newStatus === TaskStatus::COMPLETED->value && $oldStatus !== TaskStatus::COMPLETED->value) {
                $activityService->log(
                    $request->user(),
                    UserActivityType::TASK_COMPLETED,
                    "Completed task: {$updated->title}",
                    $updated
                );
            } else {
                $activityService->log(
                    $request->user(),
                    UserActivityType::TASK_UPDATED,
                    "Updated task: {$updated->title}",
                    $updated
                );
            }

            return $this->successResponse(
                new TaskResource($updated),
                __('projects.task_updated_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to update task', [
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'task_id' => $task->id,
                'operation' => 'tasks.update',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_update_task'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Change task status and position (Kanban drag and drop).
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Project $project, Task $task, TaskService $service, UserActivityService $activityService): JsonResponse
    {
        if ((int) $task->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.task_not_in_project'),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $this->authorize('updateStatus', $task);

        try {
            $oldStatus = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;
            $updated = $service->updateTaskStatus($task, $request->validated());
            $newStatus = $updated->status instanceof \BackedEnum ? $updated->status->value : $updated->status;

            if ($newStatus === TaskStatus::COMPLETED->value && $oldStatus !== TaskStatus::COMPLETED->value) {
                $activityService->log(
                    $request->user(),
                    UserActivityType::TASK_COMPLETED,
                    "Completed task: {$updated->title}",
                    $updated
                );
            } else {
                $activityService->log(
                    $request->user(),
                    UserActivityType::TASK_UPDATED,
                    "Updated task status: {$updated->title}",
                    $updated
                );
            }

            return $this->successResponse(
                new TaskResource($updated),
                __('projects.task_status_updated_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to update task status', [
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'task_id' => $task->id,
                'operation' => 'tasks.update_status',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_update_task'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Soft delete a task.
     */
    public function destroy(Request $request, Project $project, Task $task, TaskService $service): JsonResponse
    {
        if ((int) $task->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.task_not_in_project'),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $this->authorize('delete', $task);

        try {
            $service->deleteTask($task);

            return $this->successResponse(null, __('projects.task_deleted_successfully'));
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to delete task', [
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'task_id' => $task->id,
                'operation' => 'tasks.delete',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_delete_task'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
