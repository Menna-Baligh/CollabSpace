<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated user cannot list or view projects', function () {
    $this->getJson(route('api.v1.projects.index'))->assertStatus(401);

    $project = Project::factory()->create();
    $this->getJson(route('api.v1.projects.show', $project))->assertStatus(401);
});

test('authenticated user can list all non-deleted projects including archived', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $activeProject = Project::factory()->active()->create(['name' => 'Active Project Alpha']);
    $archivedProject = Project::factory()->archived()->create(['name' => 'Archived Project Beta']);
    $deletedProject = Project::factory()->create(['name' => 'Deleted Project Gamma']);
    $deletedProject->delete();

    $response = $this->getJson(route('api.v1.projects.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'icon',
                    'color',
                    'start_date',
                    'deadline',
                    'status',
                    'priority',
                    'files_count',
                    'members_count',
                    'members_preview',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Active Project Alpha')
        ->and($names)->toContain('Archived Project Beta')
        ->and($names)->not->toContain('Deleted Project Gamma');
});

test('project listing search and status filtering work correctly', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Project::factory()->create(['name' => 'Mobile App iOS', 'status' => ProjectStatus::ACTIVE]);
    Project::factory()->create(['name' => 'Website Redesign', 'status' => ProjectStatus::DONE]);
    Project::factory()->create(['name' => 'Legacy System', 'status' => ProjectStatus::ARCHIVED]);

    // Search filter
    $searchRes = $this->getJson(route('api.v1.projects.index', ['search' => 'Mobile']));
    $searchRes->assertOk();
    expect($searchRes->json('data'))->toHaveCount(1)
        ->and($searchRes->json('data.0.name'))->toBe('Mobile App iOS');

    // Status filter: active
    $activeRes = $this->getJson(route('api.v1.projects.index', ['status' => 'active']));
    $activeRes->assertOk();
    expect(collect($activeRes->json('data'))->pluck('status')->unique()->all())->toBe(['active']);

    // Status filter: done
    $doneRes = $this->getJson(route('api.v1.projects.index', ['status' => 'done']));
    $doneRes->assertOk();
    expect(collect($doneRes->json('data'))->pluck('status')->unique()->all())->toBe(['done']);

    // Status filter: all
    $allRes = $this->getJson(route('api.v1.projects.index', ['status' => 'all']));
    $allRes->assertOk();
    expect(count($allRes->json('data')))->toBeGreaterThanOrEqual(3);
});

test('project listing sorts by priority in business order (high -> medium -> low)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $low = Project::factory()->create(['name' => 'Low Priority', 'priority' => ProjectPriority::LOW]);
    $high = Project::factory()->create(['name' => 'High Priority', 'priority' => ProjectPriority::HIGH]);
    $medium = Project::factory()->create(['name' => 'Medium Priority', 'priority' => ProjectPriority::MEDIUM]);

    $response = $this->getJson(route('api.v1.projects.index', ['sort' => 'priority']));
    $response->assertOk();

    $priorities = collect($response->json('data'))->pluck('priority')->all();
    $highIndex = array_search('high', $priorities);
    $mediumIndex = array_search('medium', $priorities);
    $lowIndex = array_search('low', $priorities);

    expect($highIndex)->toBeLessThan($mediumIndex)
        ->and($mediumIndex)->toBeLessThan($lowIndex);
});

test('project details returns required schema and computes timeline progress correctly', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create([
        'name' => 'Project Timeline Test',
        'start_date' => now()->subDays(10)->format('Y-m-d'),
        'deadline' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $project->members()->attach($user->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->getJson(route('api.v1.projects.show', $project));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'project_type',
                'start_date',
                'deadline',
                'priority',
                'status',
                'color',
                'icon',
                'archived_at',
                'completion_percentage',
                'timeline_progress_percentage',
                'total_tasks',
                'pending_tasks',
                'in_progress_tasks',
                'completed_tasks',
                'total_tasks_count',
                'members' => [
                    '*' => [
                        'id',
                        'name',
                        'avatar',
                        'role',
                        'is_leader',
                    ],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['completion_percentage'])->toBe(0)
        ->and($data['total_tasks'])->toBe(0)
        ->and($data['total_tasks_count'])->toBe(0)
        ->and($data['timeline_progress_percentage'])->toBeGreaterThanOrEqual(40)
        ->and($data['timeline_progress_percentage'])->toBeLessThanOrEqual(60);
});

test('project details supports filtering members by team_id', function () {
    $user1 = User::factory()->create(['name' => 'Team 1 Leader']);
    $user2 = User::factory()->create(['name' => 'Team 2 Member']);
    Sanctum::actingAs($user1);

    $project = Project::factory()->create();
    $project->members()->attach($user1->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($user2->id, ['role' => ProjectRole::MEMBER->value]);

    $team1 = ProjectTeam::factory()->create([
        'project_id' => $project->id,
        'leader_id' => $user1->id,
    ]);
    $team1->members()->attach($user1->id, ['role_title' => 'Lead Dev']);

    $response = $this->getJson(route('api.v1.projects.show', ['project' => $project, 'team_id' => $team1->id]));

    $response->assertOk();
    $members = $response->json('data.members');
    expect($members)->toHaveCount(1)
        ->and($members[0]['id'])->toBe($user1->id)
        ->and($members[0]['is_leader'])->toBeTrue()
        ->and($members[0]['role'])->toBe('project_manager');

    // Invalid team_id for this project returns 422
    $otherProject = Project::factory()->create();
    $otherTeam = ProjectTeam::factory()->create(['project_id' => $otherProject->id]);

    $invalidRes = $this->getJson(route('api.v1.projects.show', ['project' => $project, 'team_id' => $otherTeam->id]));
    $invalidRes->assertStatus(422)
        ->assertJsonPath('error.code', 'team_not_in_project');
});
