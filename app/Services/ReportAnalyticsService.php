<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ReportType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    /**
     * Calculate Analytics: KPIs and Charts.
     *
     * @param  array{project_id?: ?int, period?: ?string}  $filters
     * @return array{
     *     kpis: array{
     *         task_completion_rate: array{percentage: int, change_from_last_month: int},
     *         projects_completion_rate: array{percentage: int, change_from_last_month: int},
     *         teams_activity_rate: array{percentage: int, change_from_last_month: int}
     *     },
     *     charts: array{
     *         task_completion_vs_deadline: list<array{month: string, completed: int, deadline_expected: int}>,
     *         projects_progress: list<array{label: string, progress: int}>,
     *         worker_activity_rate: list<array{label: string, count: int}>
     *     }
     * }
     */
    public function getAnalytics(User $user, array $filters = []): array
    {
        $projectId = ! empty($filters['project_id']) ? (int) $filters['project_id'] : null;
        $period = $filters['period'] ?? 'monthly';

        // 1. Calculate KPIs
        $kpis = [
            'task_completion_rate' => $this->calculateTaskCompletionRate($projectId),
            'projects_completion_rate' => $this->calculateProjectsCompletionRate($projectId),
            'teams_activity_rate' => $this->calculateTeamsActivityRate($projectId),
        ];

        // 2. Calculate Charts
        $charts = [
            'task_completion_vs_deadline' => $this->getTaskCompletionVsDeadlineChart($projectId),
            'projects_progress' => $this->getProjectsProgressChart($projectId),
            'worker_activity_rate' => $this->getWorkerActivityRateChart($projectId, $period),
        ];

        return [
            'kpis' => $kpis,
            'charts' => $charts,
        ];
    }

    /**
     * KPI 1: Task Completion Rate.
     * Percentage of completed tasks vs total tasks.
     * Change from last month: current month completion % minus previous month completion %.
     *
     * @return array{percentage: int, change_from_last_month: int}
     */
    protected function calculateTaskCompletionRate(?int $projectId): array
    {
        $baseQuery = Task::query();
        if ($projectId) {
            $baseQuery->where('project_id', $projectId);
        }

        $totalTasks = (clone $baseQuery)->count();
        $completedTasks = (clone $baseQuery)->where('status', TaskStatus::COMPLETED->value)->count();
        $overallPercentage = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        // Current month rates
        $startOfCurrentMonth = now()->startOfMonth();
        $endOfCurrentMonth = now()->endOfMonth();

        $currentMonthTotal = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfCurrentMonth, $endOfCurrentMonth])
            ->count();
        $currentMonthCompleted = (clone $baseQuery)
            ->where('status', TaskStatus::COMPLETED->value)
            ->whereBetween('updated_at', [$startOfCurrentMonth, $endOfCurrentMonth])
            ->count();
        $currentMonthRate = $currentMonthTotal > 0
            ? (int) round(($currentMonthCompleted / $currentMonthTotal) * 100)
            : 0;

        // Previous month rates
        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();

        $lastMonthTotal = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $lastMonthCompleted = (clone $baseQuery)
            ->where('status', TaskStatus::COMPLETED->value)
            ->whereBetween('updated_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $lastMonthRate = $lastMonthTotal > 0
            ? (int) round(($lastMonthCompleted / $lastMonthTotal) * 100)
            : 0;

        $change = $currentMonthRate - $lastMonthRate;

        return [
            'percentage' => $overallPercentage,
            'change_from_last_month' => $change,
        ];
    }

    /**
     * KPI 2: Projects Completion Rate.
     * Percentage of completed projects vs total non-archived projects.
     *
     * @return array{percentage: int, change_from_last_month: int}
     */
    protected function calculateProjectsCompletionRate(?int $projectId): array
    {
        $baseQuery = Project::query();
        if ($projectId) {
            $baseQuery->where('id', $projectId);
        }

        $totalProjects = (clone $baseQuery)->count();
        $completedProjects = (clone $baseQuery)->where('status', ProjectStatus::DONE->value)->count();
        $overallPercentage = $totalProjects > 0 ? (int) round(($completedProjects / $totalProjects) * 100) : 0;

        $startOfCurrentMonth = now()->startOfMonth();
        $endOfCurrentMonth = now()->endOfMonth();
        $currentMonthTotal = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfCurrentMonth, $endOfCurrentMonth])
            ->count();
        $currentMonthCompleted = (clone $baseQuery)
            ->where('status', ProjectStatus::DONE->value)
            ->whereBetween('updated_at', [$startOfCurrentMonth, $endOfCurrentMonth])
            ->count();
        $currentMonthRate = $currentMonthTotal > 0
            ? (int) round(($currentMonthCompleted / $currentMonthTotal) * 100)
            : 0;

        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();
        $lastMonthTotal = (clone $baseQuery)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $lastMonthCompleted = (clone $baseQuery)
            ->where('status', ProjectStatus::DONE->value)
            ->whereBetween('updated_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $lastMonthRate = $lastMonthTotal > 0
            ? (int) round(($lastMonthCompleted / $lastMonthTotal) * 100)
            : 0;

        $change = $currentMonthRate - $lastMonthRate;

        return [
            'percentage' => $overallPercentage,
            'change_from_last_month' => $change,
        ];
    }

    /**
     * KPI 3: Teams Activity Rate.
     * Active teams (with tasks in progress, review, or completed) divided by total teams.
     *
     * @return array{percentage: int, change_from_last_month: int}
     */
    protected function calculateTeamsActivityRate(?int $projectId): array
    {
        $teamsQuery = ProjectTeam::query();
        if ($projectId) {
            $teamsQuery->where('project_id', $projectId);
        }

        $totalTeams = (clone $teamsQuery)->count();

        $activeTeams = (clone $teamsQuery)->whereHas('tasks', function (Builder $q) {
            $q->whereIn('status', [
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::IN_REVIEW->value,
                TaskStatus::COMPLETED->value,
            ]);
        })->count();

        $percentage = $totalTeams > 0 ? (int) round(($activeTeams / $totalTeams) * 100) : 0;

        // Month over month change based on tasks updated in team
        $startOfCurrentMonth = now()->startOfMonth();
        $endOfCurrentMonth = now()->endOfMonth();
        $currentMonthActiveTeams = (clone $teamsQuery)->whereHas('tasks', function (Builder $q) use ($startOfCurrentMonth, $endOfCurrentMonth) {
            $q->whereBetween('updated_at', [$startOfCurrentMonth, $endOfCurrentMonth]);
        })->count();
        $currentMonthRate = $totalTeams > 0 ? (int) round(($currentMonthActiveTeams / $totalTeams) * 100) : 0;

        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();
        $lastMonthActiveTeams = (clone $teamsQuery)->whereHas('tasks', function (Builder $q) use ($startOfLastMonth, $endOfLastMonth) {
            $q->whereBetween('updated_at', [$startOfLastMonth, $endOfLastMonth]);
        })->count();
        $lastMonthRate = $totalTeams > 0 ? (int) round(($lastMonthActiveTeams / $totalTeams) * 100) : 0;

        $change = $currentMonthRate - $lastMonthRate;

        return [
            'percentage' => $percentage,
            'change_from_last_month' => $change,
        ];
    }

    /**
     * Chart 1: Task Completion vs Deadline.
     * Monthly data points for the past 6 calendar months:
     * completed: tasks completed in that month.
     * deadline_expected: tasks whose due_date fell in that month.
     *
     * @return list<array{month: string, completed: int, deadline_expected: int}>
     */
    protected function getTaskCompletionVsDeadlineChart(?int $projectId): array
    {
        $chart = [];
        $driver = DB::connection()->getDriverName();

        for ($i = 5; $i >= 0; $i--) {
            $targetMonth = now()->subMonths($i);
            $monthLabel = $targetMonth->format('M');
            $start = $targetMonth->copy()->startOfMonth();
            $end = $targetMonth->copy()->endOfMonth();

            $queryCompleted = Task::where('status', TaskStatus::COMPLETED->value)
                ->whereBetween('updated_at', [$start, $end]);

            $queryDeadline = Task::whereNotNull('due_date')
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]);

            if ($projectId) {
                $queryCompleted->where('project_id', $projectId);
                $queryDeadline->where('project_id', $projectId);
            }

            $chart[] = [
                'month' => $monthLabel,
                'completed' => $queryCompleted->count(),
                'deadline_expected' => $queryDeadline->count(),
            ];
        }

        return $chart;
    }

    /**
     * Chart 2: Projects Progress.
     * Calculates project progress for visible/selected projects.
     * Uses task completion progress or timeline progress percentage.
     *
     * @return list<array{label: string, progress: int}>
     */
    protected function getProjectsProgressChart(?int $projectId): array
    {
        $query = Project::query();
        if ($projectId) {
            $query->where('id', $projectId);
        } else {
            $query->limit(6);
        }

        $projects = $query->with('tasks:id,project_id,progress,status')->get();

        $chart = [];
        foreach ($projects as $project) {
            $tasks = $project->tasks;
            $progress = 0;

            if ($tasks->count() > 0) {
                $progress = (int) round($tasks->avg('progress'));
            } else {
                $progress = $project->timelineProgressPercentage();
            }

            $chart[] = [
                'label' => $project->name,
                'progress' => min(max($progress, 0), 100),
            ];
        }

        return $chart;
    }

    /**
     * Chart 3: Worker Activity Rate.
     * Activity counts based on user_activities / task updates for the selected period.
     *
     * @return list<array{label: string, count: int}>
     */
    protected function getWorkerActivityRateChart(?int $projectId, string $period): array
    {
        $chart = [];

        if ($period === 'weekly') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();

                $query = UserActivity::whereBetween('created_at', [$start, $end]);
                if ($projectId) {
                    $query->where(function ($q) use ($projectId) {
                        $q->where(function ($sub) use ($projectId) {
                            $sub->where('subject_type', Task::class)
                                ->whereIn('subject_id', Task::where('project_id', $projectId)->pluck('id'));
                        })->orWhere(function ($sub) use ($projectId) {
                            $sub->where('subject_type', ProjectFile::class)
                                ->whereIn('subject_id', ProjectFile::where('project_id', $projectId)->pluck('id'));
                        });
                    });
                }

                $chart[] = [
                    'label' => $date->format('D'),
                    'count' => $query->count(),
                ];
            }
        } elseif ($period === 'yearly') {
            // Last 5 years
            for ($i = 4; $i >= 0; $i--) {
                $year = now()->subYears($i);
                $start = $year->copy()->startOfYear();
                $end = $year->copy()->endOfYear();

                $query = UserActivity::whereBetween('created_at', [$start, $end]);
                if ($projectId) {
                    $query->where(function ($q) use ($projectId) {
                        $q->where(function ($sub) use ($projectId) {
                            $sub->where('subject_type', Task::class)
                                ->whereIn('subject_id', Task::where('project_id', $projectId)->pluck('id'));
                        })->orWhere(function ($sub) use ($projectId) {
                            $sub->where('subject_type', ProjectFile::class)
                                ->whereIn('subject_id', ProjectFile::where('project_id', $projectId)->pluck('id'));
                        });
                    });
                }

                $chart[] = [
                    'label' => $year->format('Y'),
                    'count' => $query->count(),
                ];
            }
        } else {
            // Monthly: Last 6 months
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();

                $query = UserActivity::whereBetween('created_at', [$start, $end]);
                if ($projectId) {
                    $query->where(function ($q) use ($projectId) {
                        $q->where(function ($sub) use ($projectId) {
                            $sub->where('subject_type', Task::class)
                                ->whereIn('subject_id', Task::where('project_id', $projectId)->pluck('id'));
                        })->orWhere(function ($sub) use ($projectId) {
                            $sub->where('subject_type', ProjectFile::class)
                                ->whereIn('subject_id', ProjectFile::where('project_id', $projectId)->pluck('id'));
                        });
                    });
                }

                $chart[] = [
                    'label' => $month->format('M'),
                    'count' => $query->count(),
                ];
            }
        }

        return $chart;
    }

    /**
     * Generate summary snapshot for submitted report.
     *
     * @return array<string, mixed>
     */
    public function generateReportSnapshot(
        ReportType|string $reportType,
        string $startDate,
        string $endDate,
        ?int $projectId = null
    ): array {
        $type = $reportType instanceof ReportType ? $reportType->value : $reportType;
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $taskQuery = Task::whereBetween('created_at', [$start, $end]);
        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
        }

        $totalTasks = (clone $taskQuery)->count();
        $completedTasks = (clone $taskQuery)->where('status', TaskStatus::COMPLETED->value)->count();
        $inProgressTasks = (clone $taskQuery)->where('status', TaskStatus::IN_PROGRESS->value)->count();

        switch ($type) {
            case ReportType::PROJECT_PROGRESS->value:
                $projectQuery = Project::query();
                if ($projectId) {
                    $projectQuery->where('id', $projectId);
                }
                $projectsCount = $projectQuery->count();
                $completedProjects = (clone $projectQuery)->where('status', ProjectStatus::DONE->value)->count();

                return [
                    'projects_evaluated' => $projectsCount,
                    'projects_completed' => $completedProjects,
                    'total_tasks_in_period' => $totalTasks,
                    'completed_tasks_in_period' => $completedTasks,
                    'completion_percentage' => $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0,
                ];

            case ReportType::TASKS_SUMMARY->value:
                return [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completion_rate' => $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0,
                ];

            case ReportType::TEAM_ACTIVITY->value:
                $teamQuery = ProjectTeam::query();
                if ($projectId) {
                    $teamQuery->where('project_id', $projectId);
                }

                return [
                    'teams_count' => $teamQuery->count(),
                    'total_tasks_associated' => $totalTasks,
                    'active_tasks' => $inProgressTasks + $completedTasks,
                ];

            case ReportType::WORKER_PERFORMANCE->value:
            default:
                $activitiesCount = UserActivity::whereBetween('created_at', [$start, $end])->count();

                return [
                    'tasks_completed' => $completedTasks,
                    'total_activities_logged' => $activitiesCount,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ];
        }
    }

    /**
     * Submit detailed report atomically.
     *
     * @param  array{report_type: string, note?: ?string, start_date: string, end_date: string, project_id?: ?int}  $data
     */
    public function submitReport(User $user, array $data): Report
    {
        return DB::transaction(function () use ($user, $data) {
            $snapshot = $this->generateReportSnapshot(
                $data['report_type'],
                $data['start_date'],
                $data['end_date'],
                $data['project_id'] ?? null
            );

            return Report::create([
                'user_id' => $user->id,
                'project_id' => $data['project_id'] ?? null,
                'report_type' => $data['report_type'],
                'note' => $data['note'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'summary_snapshot' => $snapshot,
            ]);
        });
    }

    /**
     * List user submitted reports with pagination.
     *
     * @param  array{report_type?: ?string}  $filters
     */
    public function listUserReports(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Report::where('user_id', $user->id)->with('project:id,name');

        if (! empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(min(max($perPage, 1), 100));
    }
}
