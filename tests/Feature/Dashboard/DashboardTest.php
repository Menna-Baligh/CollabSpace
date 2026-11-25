<?php

use App\Enums\MeetingStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects unauthenticated access to dashboard', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

it('rejects suspended users from dashboard', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertForbidden();
});

it('returns all expected dashboard sections and zero-task completion rate', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'kpis' => [
                'pending_tasks' => ['count', 'total', 'change_from_last_month'],
                'in_progress_tasks' => ['count', 'total', 'change_from_last_month'],
                'completed_tasks' => ['count', 'total', 'change_from_last_month'],
                'completion_rate' => ['percentage', 'change_from_last_month'],
            ],
            'project_overview_chart',
            'active_projects',
            'recent_files',
            'tasks',
            'teams' => ['selected_team', 'available_teams', 'members'],
            'upcoming_meetings',
        ],
    ]);

    expect($response->json('data.kpis.completion_rate.percentage'))->toBe(0);
    expect($response->json('data.kpis.completion_rate.change_from_last_month'))->toBeNull();
    expect($response->json('data.teams.selected_team'))->toBeNull();
});

it('calculates kpis accurately and groups in_progress and in_review', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $project->members()->attach($user->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    Task::create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'T1', 'status' => TaskStatus::TODO]);
    Task::create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'T2', 'status' => TaskStatus::IN_PROGRESS]);
    Task::create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'T3', 'status' => TaskStatus::IN_REVIEW]);
    Task::create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'T4', 'status' => TaskStatus::COMPLETED]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk();

    expect($response->json('data.kpis.pending_tasks.count'))->toBe(1);
    expect($response->json('data.kpis.in_progress_tasks.count'))->toBe(2);
    expect($response->json('data.kpis.completed_tasks.count'))->toBe(1);
    expect($response->json('data.kpis.completed_tasks.total'))->toBe(4);
    expect($response->json('data.kpis.completion_rate.percentage'))->toBe(25);
});

it('filters kpis by project_id and forbids inaccessible projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $p1 = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $p1->members()->attach($user->id, ['role' => ProjectRole::MEMBER->value]);

    $p2 = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $p2->members()->attach($otherUser->id, ['role' => ProjectRole::MEMBER->value]);

    Task::create(['project_id' => $p1->id, 'created_by' => $user->id, 'title' => 'T1', 'status' => TaskStatus::COMPLETED]);
    Task::create(['project_id' => $p2->id, 'created_by' => $otherUser->id, 'title' => 'T2', 'status' => TaskStatus::TODO]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/dashboard?project_id={$p1->id}")
        ->assertOk()
        ->assertJsonPath('data.kpis.completed_tasks.count', 1)
        ->assertJsonPath('data.kpis.pending_tasks.count', 0);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/dashboard?project_id={$p2->id}")
        ->assertForbidden();
});

it('validates period parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard?period=invalid_period')
        ->assertUnprocessable();
});

it('records task status history on creation and transition without duplicates', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $project->members()->attach($user->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $this->actingAs($user, 'sanctum');
    $taskService = app(TaskService::class);

    $task = $taskService->createTask($project, $user, [
        'title' => 'History Task',
        'status' => TaskStatus::TODO->value,
        'priority' => TaskPriority::HIGH->value,
    ]);

    expect(TaskStatusHistory::where('task_id', $task->id)->count())->toBe(1);

    // Same status should not create duplicate
    $taskService->updateTaskStatus($task, ['status' => TaskStatus::TODO->value]);
    expect(TaskStatusHistory::where('task_id', $task->id)->count())->toBe(1);

    // Transition to completed
    $taskService->updateTaskStatus($task, ['status' => TaskStatus::COMPLETED->value]);
    expect(TaskStatusHistory::where('task_id', $task->id)->count())->toBe(2);
});

it('returns teams widget with deterministic selection and forbids inaccessible team', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $project->members()->attach($user->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $team = ProjectTeam::create(['project_id' => $project->id, 'name' => 'Alpha Team', 'leader_id' => $user->id]);
    $team->members()->attach($user->id, ['role_title' => 'Lead']);

    $otherTeam = ProjectTeam::create(['project_id' => $project->id, 'name' => 'Beta Team', 'leader_id' => $otherUser->id]);
    $otherTeam->members()->attach($otherUser->id, ['role_title' => 'Dev']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.teams.selected_team.id', $team->id);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/dashboard?team_id={$otherTeam->id}")
        ->assertForbidden();
});

it('excludes cancelled and past meetings from upcoming meetings widget', function () {
    $user = User::factory()->create();

    Meeting::create([
        'creator_id' => $user->id,
        'title' => 'Upcoming Sync',
        'status' => MeetingStatus::SCHEDULED,
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
        'timezone' => 'UTC',
        'provider' => 'jitsi',
        'room_name' => 'upcoming-sync',
    ]);

    Meeting::create([
        'creator_id' => $user->id,
        'title' => 'Cancelled Sync',
        'status' => MeetingStatus::CANCELLED,
        'start_time' => now()->addDays(2),
        'end_time' => now()->addDays(2)->addHour(),
        'timezone' => 'UTC',
        'provider' => 'jitsi',
        'room_name' => 'cancelled-sync',
    ]);

    Meeting::create([
        'creator_id' => $user->id,
        'title' => 'Past Sync',
        'status' => MeetingStatus::COMPLETED,
        'start_time' => now()->subDays(2),
        'end_time' => now()->subDays(2)->addHour(),
        'timezone' => 'UTC',
        'provider' => 'jitsi',
        'room_name' => 'past-sync',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk();

    $titles = collect($response->json('data.upcoming_meetings'))->pluck('title')->all();
    expect($titles)->toBe(['Upcoming Sync']);
});
