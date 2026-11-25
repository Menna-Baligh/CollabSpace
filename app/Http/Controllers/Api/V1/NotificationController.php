<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(private readonly NotificationService $service) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:sanctum', 'active.user']),
        ];
    }

    /**
     * List paginated notifications for the authenticated user.
     */
    public function index(NotificationIndexRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filter = $request->input('filter', 'all');
            $perPage = (int) $request->input('per_page', 20);

            $notifications = $this->service->getNotifications($user, $filter, $perPage);

            return $this->successResponse(
                NotificationResource::collection($notifications)->response()->getData(true),
                __('notifications.list_success'),
            );
        } catch (Throwable $e) {
            Log::error('Failed to list notifications', ['error' => $e->getMessage()]);

            return $this->failureResponse(__('notifications.list_failed'), null, [], 500);
        }
    }

    /**
     * Get the unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = $this->service->getUnreadCount($request->user());

            return $this->successResponse(
                ['unread_count' => $count],
                __('notifications.unread_count_success'),
            );
        } catch (Throwable $e) {
            Log::error('Failed to get unread notification count', ['error' => $e->getMessage()]);

            return $this->failureResponse(__('notifications.list_failed'), null, [], 500);
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $notification = $this->service->markAsRead($request->user(), $id);

            if (! $notification) {
                return $this->failureResponse(__('notifications.not_found'), null, [], 404);
            }

            return $this->successResponse(
                new NotificationResource($notification),
                __('notifications.marked_as_read'),
            );
        } catch (Throwable $e) {
            Log::error('Failed to mark notification as read', ['error' => $e->getMessage()]);

            return $this->failureResponse(__('notifications.mark_read_failed'), null, [], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $count = $this->service->markAllAsRead($request->user());

            return $this->successResponse(
                ['marked_count' => $count],
                __('notifications.marked_all_as_read'),
            );
        } catch (Throwable $e) {
            Log::error('Failed to mark all notifications as read', ['error' => $e->getMessage()]);

            return $this->failureResponse(__('notifications.mark_read_failed'), null, [], 500);
        }
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $deleted = $this->service->deleteNotification($request->user(), $id);

            if (! $deleted) {
                return $this->failureResponse(__('notifications.not_found'), null, [], 404);
            }

            return $this->successResponse(null, __('notifications.deleted'));
        } catch (Throwable $e) {
            Log::error('Failed to delete notification', ['error' => $e->getMessage()]);

            return $this->failureResponse(__('notifications.delete_failed'), null, [], 500);
        }
    }

    /**
     * Delete all notifications for the authenticated user.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        try {
            $count = $this->service->deleteAllNotifications($request->user());

            return $this->successResponse(
                ['deleted_count' => $count],
                __('notifications.deleted_all'),
            );
        } catch (Throwable $e) {
            Log::error('Failed to delete all notifications', ['error' => $e->getMessage()]);

            return $this->failureResponse(__('notifications.delete_failed'), null, [], 500);
        }
    }
}
