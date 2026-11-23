<?php

namespace App\Http\Controllers\Api\V1\Files;

use App\Enums\UserActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Files\StoreProjectFileRequest;
use App\Http\Resources\ProjectFileResource;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\ProjectFileService;
use App\Services\UserActivityService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectFileController extends Controller
{
    use ApiResponse;

    /**
     * List recent and all files for a project.
     */
    public function index(Request $request, Project $project, ProjectFileService $service): JsonResponse
    {
        $recentFiles = $service->getRecentFiles($project);

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $service->listAllFiles($project, ['search' => $request->query('search')], $perPage);

        return $this->successResponse(
            [
                'recent_files' => ProjectFileResource::collection($recentFiles),
                'all_files' => ProjectFileResource::collection($paginator->items()),
            ],
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Upload a new file to the project.
     */
    public function store(StoreProjectFileRequest $request, Project $project, ProjectFileService $service, UserActivityService $activityService): JsonResponse
    {
        $this->authorize('upload', [ProjectFile::class, $project]);

        try {
            $file = $service->uploadFile(
                $project,
                $request->user(),
                $request->file('file'),
                $request->safe(['name'])
            );

            $activityService->log(
                $request->user(),
                UserActivityType::FILE_UPLOADED,
                "Uploaded file: {$file->name}",
                $file
            );

            return $this->successResponse(
                new ProjectFileResource($file),
                __('projects.file_uploaded_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to upload file', [
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'operation' => 'files.store',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_upload_file'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Download a project file.
     */
    public function download(Project $project, ProjectFile $file, ProjectFileService $service): BinaryFileResponse|JsonResponse
    {
        if ((int) $file->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.file_not_in_project'),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $this->authorize('view', $file);

        try {
            $downloadInfo = $service->getFileForDownload($file);

            return response()->download(
                $downloadInfo['path'],
                $downloadInfo['original_name'],
                ['Content-Type' => $downloadInfo['mime_type']]
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        } catch (Throwable $e) {
            Log::error('Failed to download file', [
                'project_id' => $project->id,
                'file_id' => $file->id,
                'operation' => 'files.download',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_download_file'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Soft delete a file.
     */
    public function destroy(Project $project, ProjectFile $file, ProjectFileService $service): JsonResponse
    {
        if ((int) $file->project_id !== (int) $project->id) {
            return $this->failureResponse(
                __('projects.file_not_in_project'),
                'not_found',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $this->authorize('delete', $file);

        try {
            $service->deleteFile($file);

            return $this->successResponse(null, __('projects.file_deleted_successfully'));
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to delete file', [
                'project_id' => $project->id,
                'file_id' => $file->id,
                'operation' => 'files.delete',
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_delete_file'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
