<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Aggregate all dashboard widgets for the authenticated user.
     *
     * @param  array{project_id?: int|null, period?: string|null, team_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user, array $filters = []): array
    {
        $projectId = isset($filters['project_id']) ? (int) $filters['project_id'] : null;
        $period = $filters['period'] ?? 'this_year';
        $teamId = isset($filters['team_id']) ? (int) $filters['team_id'] : null;

        $accessibleProjectIds = Project::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        if ($projectId !== null && ! $accessibleProjectIds->contains($projectId)) {
            throw new AuthorizationException(__('dashboard.project_forbidden'));
        }

        $scopedProjectIds = $projectId !== null ? collect([$projectId]) : $accessibleProjectIds;

        return [
            'kpis' => $this->buildKpis($scopedProjectIds),
            'project_overview_chart' => $this->buildTaskTrendChart($scopedProjectIds, $period),
            'active_projects' => $this->buildActiveProjects($accessibleProjectIds),
            'recent_files' => $this->buildRecentFiles($accessibleProjectIds),
            'tasks' => $this->buildTasksWidget($user, $accessibleProjectIds),
            'teams' => $this->buildTeamsWidget($user, $teamId),
            'upcoming_meetings' => $this->buildUpcomingMeetings($user),
        ];
    }

    /**
     * Build KPI counts and month-over-month signed absolute differences.
     *
     * @param  Collection<int, int>  $projectIds
     * @return array<string, mixed>
     */
    protected function buildKpis(Collection $projectIds): array
    {
        if ($projectIds->isEmpty()) {
            return $this->emptyKpis();
        }

        $counts = Task::query()
            ->whereIn('project_id', $projectIds)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count
            ', [
                TaskStatus::TODO->value,
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::IN_REVIEW->value,
                TaskStatus::COMPLETED->value,
            ])
            ->first();

        $total = (int) ($counts->total_count ?? 0);
        $pending = (int) ($counts->pending_count ?? 0);
        $inProgress = (int) ($counts->in_progress_count ?? 0);
        $completed = (int) ($counts->completed_count ?? 0);
        $completionRate = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $endOfLastMonth = Carbon::now('UTC')->startOfMonth()->subSecond();
        $prevSnapshot = $this->getSnapshotAt($projectIds, $endOfLastMonth);

        $pendingChange = $prevSnapshot !== null ? $pending - $prevSnapshot['pending'] : null;
        $inProgressChange = $prevSnapshot !== null ? $inProgress - $prevSnapshot['in_progress'] : null;
        $completedChange = $prevSnapshot !== null ? $completed - $prevSnapshot['completed'] : null;
        $rateChange = $prevSnapshot !== null ? $completionRate - $prevSnapshot['completion_rate'] : null;

        return [
            'pending_tasks' => [
                'count' => $pending,
                'total' => $total,
                'change_from_last_month' => $pendingChange,
            ],
            'in_progress_tasks' => [
                'count' => $inProgress,
                'total' => $total,
                'change_from_last_month' => $inProgressChange,
            ],
            'completed_tasks' => [
                'count' => $completed,
                'total' => $total,
                'change_from_last_month' => $completedChange,
            ],
            'completion_rate' => [
                'percentage' => $completionRate,
                'change_from_last_month' => $rateChange,
            ],
        ];
    }

    /**
     * Reconstruct task status counts at a specific point in time using TaskStatusHistory.
     * Returns null if no historical records exist at or before the target timestamp.
     *
     * @param  Collection<int, int>  $projectIds
     * @return array{pending: int, in_progress: int, completed: int, total: int, completion_rate: int}|null
     */
    protected function getSnapshotAt(Collection $projectIds, Carbon $at): ?array
    {
        $taskIds = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('created_at', '<=', $at)
            ->pluck('id');

        if ($taskIds->isEmpty()) {
            return null;
        }

        $latestHistoryIds = TaskStatusHistory::query()
            ->whereIn('task_id', $taskIds)
            ->where('changed_at', '<=', $at)
            ->selectRaw('MAX(id) as max_id')
            ->groupBy('task_id')
            ->pluck('max_id');

        if ($latestHistoryIds->isEmpty()) {
            return null;
        }

        $statuses = TaskStatusHistory::query()
            ->whereIn('id', $latestHistoryIds)
            ->pluck('to_status');

        $pending = 0;
        $inProgress = 0;
        $completed = 0;

        foreach ($statuses as $status) {
            $val = $status instanceof TaskStatus ? $status->value : (string) $status;
            if ($val === TaskStatus::TODO->value) {
                $pending++;
            } elseif (in_array($val, [TaskStatus::IN_PROGRESS->value, TaskStatus::IN_REVIEW->value], true)) {
                $inProgress++;
            } elseif ($val === TaskStatus::COMPLETED->value) {
                $completed++;
            }
        }

        $total = $pending + $inProgress + $completed;
        $rate = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return [
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'total' => $total,
            'completion_rate' => $rate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyKpis(): array
    {
        return [
            'pending_tasks' => ['count' => 0, 'total' => 0, 'change_from_last_month' => null],
            'in_progress_tasks' => ['count' => 0, 'total' => 0, 'change_from_last_month' => null],
            'completed_tasks' => ['count' => 0, 'total' => 0, 'change_from_last_month' => null],
            'completion_rate' => ['percentage' => 0, 'change_from_last_month' => null],
        ];
    }

    /**
     * Build chronological Task Trend chart data.
     *
     * @param  Collection<int, int>  $projectIds
     * @return list<array<string, mixed>>
     */
    protected function buildTaskTrendChart(Collection $projectIds, string $period): array
    {
        $now = Carbon::now('UTC');
        $buckets = [];

        if ($period === 'this_year') {
            $year = $now->year;
            for ($m = 1; $m <= 12; $m++) {
                $end = Carbon::create($year, $m, 1, 0, 0, 0, 'UTC')->endOfMonth();
                $buckets[] = ['label' => $end->format('M'), 'end' => $end];
            }
        } elseif ($period === 'this_month') {
            $daysInMonth = $now->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $end = Carbon::create($now->year, $now->month, $d, 23, 59, 59, 'UTC');
                $buckets[] = ['label' => $end->format('Y-m-d'), 'end' => $end];
            }
        } else {
            // this_week (Mon..Sun)
            $startOfWeek = $now->copy()->startOfWeek();
            for ($i = 0; $i < 7; $i++) {
                $end = $startOfWeek->copy()->addDays($i)->endOfDay();
                $buckets[] = ['label' => $end->format('D'), 'end' => $end];
            }
        }

        if ($projectIds->isEmpty()) {
            return array_map(fn ($b) => [
                'period' => $b['label'],
                'completed' => 0,
                'not_completed' => 0,
                'total' => 0,
                'history_available' => true,
            ], $buckets);
        }

        $earliestHistory = TaskStatusHistory::query()
            ->whereHas('task', fn ($q) => $q->whereIn('project_id', $projectIds))
            ->min('changed_at');

        $earliestCarbon = $earliestHistory ? Carbon::parse($earliestHistory, 'UTC') : null;

        $result = [];
        foreach ($buckets as $bucket) {
            /** @var Carbon $end */
            $end = $bucket['end'];

            if ($end->isFuture()) {
                $result[] = [
                    'period' => $bucket['label'],
                    'completed' => null,
                    'not_completed' => null,
                    'total' => null,
                    'history_available' => false,
                ];

                continue;
            }

            if ($earliestCarbon === null || $end->lt($earliestCarbon)) {
                $result[] = [
                    'period' => $bucket['label'],
                    'completed' => null,
                    'not_completed' => null,
                    'total' => null,
                    'history_available' => false,
                ];

                continue;
            }

            $snapshot = $this->getSnapshotAt($projectIds, $end);
            if ($snapshot === null) {
                $result[] = [
                    'period' => $bucket['label'],
                    'completed' => 0,
                    'not_completed' => 0,
                    'total' => 0,
                    'history_available' => true,
                ];
            } else {
                $result[] = [
                    'period' => $bucket['label'],
                    'completed' => $snapshot['completed'],
                    'not_completed' => $snapshot['pending'] + $snapshot['in_progress'],
                    'total' => $snapshot['total'],
                    'history_available' => true,
                ];
            }
        }

        return $result;
    }

    /**
     * Top 3 active projects ordered by latest updated_at.
     *
     * @param  Collection<int, int>  $projectIds
     * @return list<array<string, mixed>>
     */
    protected function buildActiveProjects(Collection $projectIds): array
    {
        if ($projectIds->isEmpty()) {
            return [];
        }

        return Project::query()
            ->whereIn('id', $projectIds)
            ->where('status', ProjectStatus::ACTIVE->value)
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', TaskStatus::COMPLETED->value),
            ])
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get(['id', 'name'])
            ->map(function (Project $project) {
                $total = (int) $project->total_tasks_count;
                $completed = (int) $project->completed_tasks_count;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'completed_tasks_count' => $completed,
                    'total_tasks_count' => $total,
                    'completion_percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Latest 3 uploaded files in accessible projects.
     *
     * @param  Collection<int, int>  $projectIds
     * @return list<array<string, mixed>>
     */
    protected function buildRecentFiles(Collection $projectIds): array
    {
        if ($projectIds->isEmpty()) {
            return [];
        }

        return ProjectFile::query()
            ->whereIn('project_id', $projectIds)
            ->with('uploader:id,name')
            ->latest('created_at')
            ->limit(3)
            ->get(['id', 'project_id', 'uploaded_by', 'name', 'extension', 'created_at'])
            ->map(fn (ProjectFile $file) => [
                'id' => $file->id,
                'project_id' => $file->project_id,
                'name' => $file->name,
                'extension' => $file->extension,
                'uploaded_by' => $file->uploader ? [
                    'id' => $file->uploader->id,
                    'name' => $file->uploader->name,
                ] : null,
                'created_at' => $file->created_at?->toIso8601String(),
                'download_url' => url("/api/v1/files/{$file->id}/download"),
            ])
            ->values()
            ->all();
    }

    /**
     * Top 3 relevant tasks assigned to or reviewed by the user.
     *
     * @param  Collection<int, int>  $projectIds
     * @return list<array<string, mixed>>
     */
    protected function buildTasksWidget(User $user, Collection $projectIds): array
    {
        if ($projectIds->isEmpty()) {
            return [];
        }

        $today = Carbon::now('UTC')->toDateString();

        return Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->where(function ($q) use ($user) {
                $q->where('reviewer_id', $user->id)
                    ->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $user->id));
            })
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END ASC', [$today])
            ->orderByRaw('CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END ASC', [
                TaskPriority::HIGH->value,
                TaskPriority::MEDIUM->value,
                TaskPriority::LOW->value,
            ])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_date', 'asc')
            ->limit(3)
            ->get(['id', 'project_id', 'title', 'priority', 'due_date', 'progress', 'status'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'title' => $task->title,
                'priority' => $task->priority?->value,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'progress' => $task->progress,
                'status' => $task->status?->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Teams widget with deterministic selection and member task stats.
     *
     * @return array<string, mixed>
     */
    protected function buildTeamsWidget(User $user, ?int $teamId): array
    {
        $availableTeams = ProjectTeam::query()
            ->where(function ($q) use ($user) {
                $q->where('leader_id', $user->id)
                    ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
            })
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'leader_id']);

        if ($teamId !== null && ! $availableTeams->contains('id', $teamId)) {
            throw new AuthorizationException(__('dashboard.team_forbidden'));
        }

        $selectedTeam = $teamId !== null
            ? $availableTeams->firstWhere('id', $teamId)
            : $availableTeams->first();

        if (! $selectedTeam) {
            return [
                'selected_team' => null,
                'available_teams' => [],
                'members' => [],
            ];
        }

        $members = $selectedTeam->members()
            ->select(['users.id', 'users.name', 'users.avatar'])
            ->get();
        $memberIds = $members->pluck('id');

        $taskStats = collect();
        if ($memberIds->isNotEmpty()) {
            $taskStats = DB::table('task_user')
                ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
                ->whereIn('task_user.user_id', $memberIds)
                ->where('tasks.project_team_id', $selectedTeam->id)
                ->whereNull('tasks.deleted_at')
                ->groupBy('task_user.user_id')
                ->selectRaw('
                    task_user.user_id,
                    COUNT(*) as assigned_count,
                    SUM(CASE WHEN tasks.status = ? THEN 1 ELSE 0 END) as completed_count
                ', [TaskStatus::COMPLETED->value])
                ->get()
                ->keyBy('user_id');
        }

        $formattedMembers = $members->map(function (User $member) use ($selectedTeam, $taskStats) {
            $stat = $taskStats->get($member->id);
            $assigned = (int) ($stat->assigned_count ?? 0);
            $completed = (int) ($stat->completed_count ?? 0);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'avatar_url' => $member->avatar_url,
                'is_leader' => (int) $selectedTeam->leader_id === (int) $member->id,
                'completed_tasks_count' => $completed,
                'assigned_tasks_count' => $assigned,
                'completion_percentage' => $assigned > 0 ? (int) round(($completed / $assigned) * 100) : 0,
            ];
        })->values()->all();

        return [
            'selected_team' => [
                'id' => $selectedTeam->id,
                'name' => $selectedTeam->name,
            ],
            'available_teams' => $availableTeams->map(fn (ProjectTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()->all(),
            'members' => $formattedMembers,
        ];
    }

    /**
     * Top 3 nearest upcoming meetings for the user.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildUpcomingMeetings(User $user): array
    {
        return Meeting::query()
            ->forUser($user)
            ->where('status', '!=', MeetingStatus::CANCELLED->value)
            ->where('end_time', '>', now())
            ->with(['participants:id,name,avatar'])
            ->orderBy('start_time', 'asc')
            ->limit(3)
            ->get(['id', 'title', 'start_time', 'end_time', 'join_url'])
            ->map(function (Meeting $meeting) {
                $participants = $meeting->participants;
                $preview = $participants->take(3)->map(fn (User $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'avatar_url' => $p->avatar_url ?? $p->avatar,
                ])->values()->all();

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'meeting_date' => $meeting->start_time?->format('Y-m-d'),
                    'formatted_time' => $meeting->start_time?->format('h:i A'),
                    'participants_preview' => $preview,
                    'extra_participants_count' => max(0, $participants->count() - 3),
                    'meeting_url' => $meeting->join_url,
                ];
            })
            ->values()
            ->all();
    }
}
