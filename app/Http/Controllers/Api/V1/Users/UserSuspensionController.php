<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SuspendUserRequest;
use App\Models\User;
use App\Services\UserSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserSuspensionController extends Controller
{
    /**
     * Suspend a user account with mandatory project handover verification.
     * Restricted to system administrators only.
     */
    public function __invoke(SuspendUserRequest $request, User $user, UserSuspensionService $service): JsonResponse
    {
        $this->authorize('suspend', $user);

        try {
            $result = $service->suspend($user, $request->validated('handovers'));

            if (! empty($result['handover_required'])) {
                return $this->failureResponse(
                    __('projects.handover_required'),
                    'handover_required',
                    ['projects' => $result['projects']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return $this->successResponse(
                null,
                __('projects.user_suspended_successfully')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failureResponse(
                $e->getMessage(),
                'validation_failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $e) {
            Log::error('Failed to suspend user', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_user_id' => $request->user()?->id,
                'target_user_id' => $user->id,
                'operation' => 'user.suspend',
            ]);

            return $this->failureResponse(
                __('projects.unexpected_error_suspend_user'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
