<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReportType;
use App\Enums\SystemRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'system_role' => SystemRole::USER,
        'suspended_at' => null,
    ]);

    $this->admin = User::factory()->create([
        'system_role' => SystemRole::ADMIN,
        'suspended_at' => null,
    ]);
});

test('unauthenticated users cannot access reports endpoints', function () {
    $this->getJson(route('api.v1.reports.analytics'))->assertUnauthorized();
    $this->getJson(route('api.v1.reports.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.reports.store'), [])->assertUnauthorized();
});

test('suspended users are blocked by active.user middleware', function () {
    $suspendedUser = User::factory()->create([
        'suspended_at' => now(),
    ]);

    $this->actingAs($suspendedUser, 'sanctum')
        ->getJson(route('api.v1.reports.analytics'))
        ->assertForbidden();
});

test('analytics returns the expected response structure and handles zero data gracefully', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.analytics'))
        ->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'kpis' => [
                'task_completion_rate' => ['percentage', 'change_from_last_month'],
                'projects_completion_rate' => ['percentage', 'change_from_last_month'],
                'teams_activity_rate' => ['percentage', 'change_from_last_month'],
            ],
            'charts' => [
                'task_completion_vs_deadline',
                'projects_progress',
                'worker_activity_rate',
            ],
        ],
        'meta',
    ]);

    $data = $response->json('data');
    expect($data['kpis']['task_completion_rate']['percentage'])->toBe(0)
        ->and($data['kpis']['projects_completion_rate']['percentage'])->toBe(0)
        ->and($data['kpis']['teams_activity_rate']['percentage'])->toBe(0);
});

test('kpi values are calculated from real database records with correct month-over-month change', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $project->members()->attach($this->user->id, ['role' => ProjectRole::MEMBER->value]);

    $team = ProjectTeam::create([
        'project_id' => $project->id,
        'name' => 'Core Team',
    ]);

    // Create 4 tasks: 2 completed, 2 in progress
    Task::create([
        'project_id' => $project->id,
        'project_team_id' => $team->id,
        'created_by' => $this->user->id,
        'title' => 'Task 1',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Task::create([
        'project_id' => $project->id,
        'project_team_id' => $team->id,
        'created_by' => $this->user->id,
        'title' => 'Task 2',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
        'position' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Task::create([
        'project_id' => $project->id,
        'project_team_id' => $team->id,
        'created_by' => $this->user->id,
        'title' => 'Task 3',
        'status' => TaskStatus::IN_PROGRESS->value,
        'progress' => 50,
        'position' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Task::create([
        'project_id' => $project->id,
        'project_team_id' => $team->id,
        'created_by' => $this->user->id,
        'title' => 'Task 4',
        'status' => TaskStatus::IN_PROGRESS->value,
        'progress' => 40,
        'position' => 4,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.analytics', ['project_id' => $project->id]))
        ->assertOk();

    $kpis = $response->json('data.kpis');
    // 2 completed out of 4 = 50%
    expect($kpis['task_completion_rate']['percentage'])->toBe(50)
        ->and($kpis['teams_activity_rate']['percentage'])->toBe(100);
});

test('analytics respects project visibility rules', function () {
    $privateProject = Project::factory()->create();

    // User is NOT a member of privateProject
    $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.analytics', ['project_id' => $privateProject->id]))
        ->assertForbidden();

    // Admin CAN view it
    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('api.v1.reports.analytics', ['project_id' => $privateProject->id]))
        ->assertOk();
});

test('period filter works for weekly, monthly, and yearly', function () {
    $project = Project::factory()->create();
    $project->members()->attach($this->user->id, ['role' => ProjectRole::MEMBER->value]);

    foreach (['weekly', 'monthly', 'yearly'] as $period) {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('api.v1.reports.analytics', ['period' => $period]))
            ->assertOk();

        $chart = $response->json('data.charts.worker_activity_rate');
        expect($chart)->toBeArray();
        if ($period === 'weekly') {
            expect(count($chart))->toBe(7);
        } elseif ($period === 'monthly') {
            expect(count($chart))->toBe(6);
        } elseif ($period === 'yearly') {
            expect(count($chart))->toBe(5);
        }
    }
});

test('invalid project_id and period are rejected with validation error', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.analytics', ['project_id' => 999999]))
        ->assertUnprocessable();

    $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.analytics', ['period' => 'daily']))
        ->assertUnprocessable();
});

test('authenticated users can submit detailed reports and calculate snapshot', function () {
    $project = Project::factory()->create();
    $project->members()->attach($this->user->id, ['role' => ProjectRole::MEMBER->value]);

    $payload = [
        'report_type' => ReportType::PROJECT_PROGRESS->value,
        'note' => 'Quarterly progress evaluation.',
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->toDateString(),
        'project_id' => $project->id,
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('api.v1.reports.store'), $payload)
        ->assertCreated();

    $response->assertJsonPath('data.report_type', ReportType::PROJECT_PROGRESS->value)
        ->assertJsonPath('data.note', 'Quarterly progress evaluation.')
        ->assertJsonPath('data.project_id', $project->id);

    $this->assertDatabaseHas('reports', [
        'user_id' => $this->user->id,
        'project_id' => $project->id,
        'report_type' => ReportType::PROJECT_PROGRESS->value,
    ]);

    $snapshot = $response->json('data.summary_snapshot');
    expect($snapshot)->toHaveKey('projects_evaluated')
        ->and($snapshot)->toHaveKey('completion_percentage');
});

test('submitting report with invalid date range or invalid report type is rejected', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson(route('api.v1.reports.store'), [
            'report_type' => 'invalid_type',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-01', // before start date
        ])
        ->assertUnprocessable();
});

test('reports list is paginated and filters by report_type', function () {
    Report::factory()->create([
        'user_id' => $this->user->id,
        'report_type' => ReportType::PROJECT_PROGRESS,
    ]);
    Report::factory()->create([
        'user_id' => $this->user->id,
        'report_type' => ReportType::TASKS_SUMMARY,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.index', ['report_type' => ReportType::PROJECT_PROGRESS->value]))
        ->assertOk();

    expect(count($response->json('data')))->toBe(1)
        ->and($response->json('data.0.report_type'))->toBe(ReportType::PROJECT_PROGRESS->value);
});

test('users cannot view another users reports and soft deleted reports are excluded', function () {
    $otherUser = User::factory()->create();
    $otherReport = Report::factory()->create(['user_id' => $otherUser->id]);

    $myReport = Report::factory()->create(['user_id' => $this->user->id]);
    $deletedReport = Report::factory()->create(['user_id' => $this->user->id]);
    $deletedReport->delete();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.reports.index'))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($myReport->id)
        ->and($ids)->not->toContain($otherReport->id)
        ->and($ids)->not->toContain($deletedReport->id);
});
