<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardRequest;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(DashboardRequest $request): JsonResponse
    {
        try {
            $data = $this->dashboardService->getDashboardData(
                $request->user(),
                $request->validated()
            );

            return $this->successResponse($data, __('dashboard.fetched'));
        } catch (AuthorizationException $e) {
            return $this->failureResponse($e->getMessage(), 'forbidden', [], 403);
        } catch (Throwable $e) {
            Log::error('Dashboard fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failureResponse(__('dashboard.fetch_failed'), 'server_error', [], 500);
        }
    }
}
