<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileSettingsRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\ProfileTaskResource;
use App\Http\Resources\ProjectFileResource;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\UserSettingsResource;
use App\Services\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserProfileController extends Controller
{
    use ApiResponse;

    /**
     * Show current authenticated user's profile overview.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse(
            new UserProfileResource($user)
        );
    }

    /**
     * Get tasks assigned to the user grouped into todo and done.
     */
    public function tasks(Request $request, ProfileService $service): JsonResponse
    {
        $user = $request->user();
        $tasksData = $service->getUserTasks($user);

        return $this->successResponse([
            'todo' => [
                'count' => $tasksData['todo']['count'],
                'data' => ProfileTaskResource::collection($tasksData['todo']['data']),
            ],
            'done' => [
                'count' => $tasksData['done']['count'],
                'data' => ProfileTaskResource::collection($tasksData['done']['data']),
            ],
        ]);
    }

    /**
     * Get files relevant to the authenticated user.
     */
    public function files(Request $request, ProfileService $service): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 15);
        $paginator = $service->getUserFiles($user, $perPage);

        return $this->successResponse(
            ProjectFileResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Show editable settings for current user.
     */
    public function showSettings(Request $request, ProfileService $service): JsonResponse
    {
        $user = $request->user();
        $currentTeamProject = $service->getCurrentTeamProject($user);

        return $this->successResponse(
            (new UserSettingsResource($user))->withCurrentTeamProject($currentTeamProject)
        );
    }

    /**
     * Update settings for current user.
     */
    public function updateSettings(UpdateProfileSettingsRequest $request, ProfileService $service): JsonResponse
    {
        $user = $request->user();

        try {
            $updatedUser = $service->updateSettings($user, $request->validated());
            $currentTeamProject = $service->getCurrentTeamProject($updatedUser);

            return $this->successResponse(
                (new UserSettingsResource($updatedUser))->withCurrentTeamProject($currentTeamProject),
                __('profile.settings_updated_successfully')
            );
        } catch (Throwable $e) {
            Log::error('Failed to update profile settings', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('profile.unexpected_error_update_settings'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Upload and update avatar for current user.
     */
    public function uploadAvatar(UploadAvatarRequest $request, ProfileService $service): JsonResponse
    {
        $user = $request->user();

        try {
            $avatarUrl = $service->updateAvatar($user, $request->file('avatar'));

            return $this->successResponse(
                ['avatar_url' => $avatarUrl],
                __('profile.avatar_uploaded_successfully')
            );
        } catch (Throwable $e) {
            Log::error('Failed to upload user avatar', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('profile.unexpected_error_upload_avatar'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
