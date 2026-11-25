<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class WorkspaceContextBuilder
{
    private const MAX_RECORDS = 15;

    /**
     * Strict allowlist of workspace operations.
     *
     * @var list<string>
     */
    public const ALLOWED_OPERATIONS = [
        'get_my_tasks',
        'get_overdue_tasks',
        'get_project_summary',
        'get_team_overview',
        'get_upcoming_meetings',
        'search_workspace',
    ];

    public function __construct(
        protected GlobalSearchService $searchService
    ) {}

    /**
     * Return Gemini functionDeclarations for workspace operations.
     *
     * @return list<array<string, mixed>>
     */
    public function getToolDeclarations(): array
    {
        return [
            [
                'name' => 'get_my_tasks',
                'description' => 'Get the authenticated user\'s active tasks (assigned to or reviewed by them).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'status' => ['type' => 'STRING', 'description' => 'Optional filter: todo, in_progress, in_review, completed'],
                    ],
                ],
            ],
            [
                'name' => 'get_overdue_tasks',
                'description' => 'Get overdue non-completed tasks in projects accessible to the user.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object) [],
                ],
            ],
            [
                'name' => 'get_project_summary',
                'description' => 'Get summary and task counts for accessible projects, or a specific project by ID or name.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_id' => ['type' => 'INTEGER', 'description' => 'Optional project ID'],
                        'project_name' => ['type' => 'STRING', 'description' => 'Optional project name search'],
                    ],
                ],
            ],
            [
                'name' => 'get_team_overview',
                'description' => 'Get teams accessible to the user and their members with task counts.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'team_id' => ['type' => 'INTEGER', 'description' => 'Optional team ID'],
                    ],
                ],
            ],
            [
                'name' => 'get_upcoming_meetings',
                'description' => 'Get upcoming non-cancelled meetings for the user.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object) [],
                ],
            ],
            [
                'name' => 'search_workspace',
                'description' => 'Search across accessible projects, tasks, files, and meetings by keyword.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING', 'description' => 'Search keyword'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    /**
     * Execute an allowlisted workspace operation for the authenticated user.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function executeOperation(User $user, string $operation, array $args = []): array
    {
        if (! in_array($operation, self::ALLOWED_OPERATIONS, true)) {
            return ['error' => 'Operation not permitted.'];
        }

        return match ($operation) {
            'get_my_tasks' => $this->getMyTasks($user, $args),
            'get_overdue_tasks' => $this->getOverdueTasks($user),
            'get_project_summary' => $this->getProjectSummary($user, $args),
            'get_team_overview' => $this->getTeamOverview($user, $args),
            'get_upcoming_meetings' => $this->getUpcomingMeetings($user),
            'search_workspace' => $this->searchWorkspace($user, $args),
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function getMyTasks(User $user, array $args): array
    {
        $query = Task::query()
            ->whereHas('project.members', fn ($q) => $q->where('users.id', $user->id))
            ->where(function ($q) use ($user) {
                $q->where('reviewer_id', $user->id)
                    ->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $user->id));
            });

        if (! empty($args['status']) && in_array($args['status'], TaskStatus::values(), true)) {
            $query->where('status', $args['status']);
        }

        $tasks = $query->with('project:id,name')
            ->orderBy('due_date', 'asc')
            ->limit(self::MAX_RECORDS)
            ->get(['id', 'project_id', 'title', 'status', 'priority', 'due_date', 'progress'])
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'project' => $t->project?->name,
                'title' => $t->title,
                'status' => $t->status?->value,
                'priority' => $t->priority?->value,
                'due_date' => $t->due_date?->format('Y-m-d'),
                'progress' => $t->progress,
            ])->all();

        return ['tasks' => $tasks];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOverdueTasks(User $user): array
    {
        $today = Carbon::now('UTC')->toDateString();

        $tasks = Task::query()
            ->whereHas('project.members', fn ($q) => $q->where('users.id', $user->id))
            ->where('status', '!=', TaskStatus::COMPLETED->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->with('project:id,name')
            ->orderBy('due_date', 'asc')
            ->limit(self::MAX_RECORDS)
            ->get(['id', 'project_id', 'title', 'status', 'priority', 'due_date'])
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'project' => $t->project?->name,
                'title' => $t->title,
                'status' => $t->status?->value,
                'priority' => $t->priority?->value,
                'due_date' => $t->due_date?->format('Y-m-d'),
            ])->all();

        return ['overdue_tasks' => $tasks];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function getProjectSummary(User $user, array $args): array
    {
        $query = Project::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn ($q) => $q->where('status', TaskStatus::COMPLETED->value),
                'tasks as pending_tasks' => fn ($q) => $q->where('status', TaskStatus::TODO->value),
            ]);

        if (! empty($args['project_id']) && is_numeric($args['project_id'])) {
            $query->where('id', (int) $args['project_id']);
        } elseif (! empty($args['project_name']) && is_string($args['project_name'])) {
            $like = '%'.addcslashes(trim($args['project_name']), '%_\\').'%';
            $query->where('name', 'like', $like);
        }

        $projects = $query->limit(self::MAX_RECORDS)
            ->get(['id', 'name', 'status', 'priority', 'deadline'])
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status?->value,
                'priority' => $p->priority?->value,
                'deadline' => $p->deadline?->format('Y-m-d'),
                'total_tasks' => (int) $p->total_tasks,
                'completed_tasks' => (int) $p->completed_tasks,
                'pending_tasks' => (int) $p->pending_tasks,
            ])->all();

        return ['projects' => $projects];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function getTeamOverview(User $user, array $args): array
    {
        $query = ProjectTeam::query()
            ->where(function ($q) use ($user) {
                $q->where('leader_id', $user->id)
                    ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
            })
            ->with(['members:id,name', 'leader:id,name']);

        if (! empty($args['team_id']) && is_numeric($args['team_id'])) {
            $query->where('id', (int) $args['team_id']);
        }

        $teams = $query->limit(self::MAX_RECORDS)
            ->get(['id', 'name', 'leader_id'])
            ->map(fn (ProjectTeam $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'leader' => $team->leader?->name,
                'members_count' => $team->members->count(),
                'members' => $team->members->pluck('name')->all(),
            ])->all();

        return ['teams' => $teams];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUpcomingMeetings(User $user): array
    {
        $meetings = Meeting::query()
            ->forUser($user)
            ->where('status', '!=', MeetingStatus::CANCELLED->value)
            ->where('end_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->limit(self::MAX_RECORDS)
            ->get(['id', 'title', 'start_time', 'end_time', 'status'])
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'start_time' => $m->start_time?->toIso8601String(),
                'end_time' => $m->end_time?->toIso8601String(),
                'status' => $m->status?->value,
            ])->all();

        return ['upcoming_meetings' => $meetings];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function searchWorkspace(User $user, array $args): array
    {
        $q = isset($args['query']) && is_string($args['query']) ? trim($args['query']) : '';
        if (mb_strlen($q) < 2) {
            return ['error' => 'Search query must be at least 2 characters.'];
        }

        return $this->searchService->search($user, mb_substr($q, 0, 100));
    }
}
