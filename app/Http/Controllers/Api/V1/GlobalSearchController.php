<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\GlobalSearchRequest;
use App\Services\GlobalSearchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class GlobalSearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected GlobalSearchService $searchService
    ) {}

    public function index(GlobalSearchRequest $request): JsonResponse
    {
        try {
            $results = $this->searchService->search(
                $request->user(),
                $request->validated('q')
            );

            return $this->successResponse($results, __('search.completed'));
        } catch (Throwable $e) {
            Log::error('Global search failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failureResponse(__('search.failed'), 'server_error', [], 500);
        }
    }
}
