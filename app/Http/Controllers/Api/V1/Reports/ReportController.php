<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GetReportAnalyticsRequest;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Project;
use App\Services\ReportAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Get analytics KPIs and Charts.
     */
    public function analytics(GetReportAnalyticsRequest $request, ReportAnalyticsService $service): JsonResponse
    {
        $user = $request->user();
        $projectId = $request->query('project_id');

        if ($projectId) {
            $project = Project::find($projectId);
            if (! $project) {
                return $this->failureResponse(
                    __('projects.not_found'),
                    'not_found',
                    status: Response::HTTP_NOT_FOUND
                );
            }

            if (! $user->isAdmin() && ! $project->hasMember($user)) {
                return $this->failureResponse(
                    __('projects.unauthorized_access'),
                    'forbidden',
                    status: Response::HTTP_FORBIDDEN
                );
            }
        }

        $analytics = $service->getAnalytics($user, $request->validated());

        return $this->successResponse($analytics);
    }

    /**
     * List submitted reports for the authenticated user.
     */
    public function index(Request $request, ReportAnalyticsService $service): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 15);
        $filters = [
            'report_type' => $request->query('report_type'),
        ];

        $paginator = $service->listUserReports($user, $filters, $perPage);

        return $this->successResponse(
            ReportResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Submit a detailed report.
     */
    public function store(StoreReportRequest $request, ReportAnalyticsService $service): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! empty($data['project_id'])) {
            $project = Project::find($data['project_id']);
            if (! $project) {
                return $this->failureResponse(
                    __('projects.not_found'),
                    'not_found',
                    status: Response::HTTP_NOT_FOUND
                );
            }

            if (! $user->isAdmin() && ! $project->hasMember($user)) {
                return $this->failureResponse(
                    __('projects.unauthorized_access'),
                    'forbidden',
                    status: Response::HTTP_FORBIDDEN
                );
            }
        }

        try {
            $report = $service->submitReport($user, $data);

            return $this->successResponse(
                new ReportResource($report),
                __('reports.created_successfully'),
                status: Response::HTTP_CREATED
            );
        } catch (Throwable $e) {
            Log::error('Failed to submit report', [
                'user_id' => $user->id,
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return $this->failureResponse(
                __('reports.unexpected_error_create_report'),
                'server_error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
