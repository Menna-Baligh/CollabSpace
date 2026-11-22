<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('any authenticated user can list project teams', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->active()->create();
    $team = ProjectTeam::factory()->create([
        'project_id' => $project->id,
        'name' => 'Design Team',
    ]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/teams");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'color',
                    'completion_percentage',
                    'leader',
                    'members',
                ],
            ],
        ]);

    expect($response->json('data.0.name'))->toBe('Design Team');
});

test('project manager can create a team with leader automatically added to team membership', function () {
    $pm = User::factory()->create();
    $leader = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($leader->id, ['role' => ProjectRole::MEMBER->value]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/teams", [
        'name' => 'Backend Engineers',
        'color' => '#3B82F6',
        'leader_id' => $leader->id,
        'leader_role_title' => 'Principal Architect',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Backend Engineers')
        ->assertJsonPath('data.leader.id', $leader->id)
        ->assertJsonPath('data.leader.role_title', 'Principal Architect');

    $team = ProjectTeam::where('name', 'Backend Engineers')->first();
    expect($team->hasMember($leader))->toBeTrue();
});

test('team leader must already be a member of the project', function () {
    $pm = User::factory()->create();
    $outsider = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/teams", [
        'name' => 'Frontend Team',
        'leader_id' => $outsider->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.team_leader_must_be_project_member'));
});

test('mandatory requirement: team names must be unique within the same project, but can be reused across projects', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project1 = Project::factory()->active()->create();
    $project1->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $project2 = Project::factory()->active()->create();
    $project2->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    // 1. Create team in Project 1
    $res1 = $this->postJson("/api/v1/projects/{$project1->id}/teams", ['name' => 'Core Team']);
    $res1->assertCreated();

    // 2. Creating another team with the SAME name in Project 1 fails
    $resDuplicate = $this->postJson("/api/v1/projects/{$project1->id}/teams", ['name' => 'Core Team']);
    $resDuplicate->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['name']]]);

    // 3. Creating team with the SAME name in Project 2 succeeds
    $res2 = $this->postJson("/api/v1/projects/{$project2->id}/teams", ['name' => 'Core Team']);
    $res2->assertCreated();

    // 4. Renaming a team to another existing team name in the same project fails
    $team2 = ProjectTeam::factory()->create(['project_id' => $project1->id, 'name' => 'QA Team']);
    $renameFail = $this->patchJson("/api/v1/projects/{$project1->id}/teams/{$team2->id}", ['name' => 'Core Team']);
    $renameFail->assertStatus(422);

    // 5. Renaming a team to an available name succeeds
    $renameSuccess = $this->patchJson("/api/v1/projects/{$project1->id}/teams/{$team2->id}", ['name' => 'Quality Assurance Team']);
    $renameSuccess->assertOk()
        ->assertJsonPath('data.name', 'Quality Assurance Team');
});

test('project manager can change team leader while previous leader remains in team', function () {
    $pm = User::factory()->create();
    $oldLeader = User::factory()->create();
    $newLeader = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($oldLeader->id, ['role' => ProjectRole::MEMBER->value]);
    $project->members()->attach($newLeader->id, ['role' => ProjectRole::MEMBER->value]);

    $team = ProjectTeam::factory()->create([
        'project_id' => $project->id,
        'leader_id' => $oldLeader->id,
    ]);
    $team->members()->attach($oldLeader->id);

    // Change leader to newLeader
    $response = $this->patchJson("/api/v1/projects/{$project->id}/teams/{$team->id}", [
        'leader_id' => $newLeader->id,
        'leader_role_title' => 'New Lead',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.leader.id', $newLeader->id);

    $team->refresh();
    expect((int) $team->leader_id)->toBe($newLeader->id)
        ->and($team->hasMember($newLeader))->toBeTrue()
        ->and($team->hasMember($oldLeader))->toBeTrue();
});

test('project manager can add and remove team members, clearing leader if leader is removed', function () {
    $pm = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($member->id, ['role' => ProjectRole::MEMBER->value]);

    $team = ProjectTeam::factory()->create(['project_id' => $project->id]);

    // Outsider cannot be added to team
    $failRes = $this->postJson("/api/v1/projects/{$project->id}/teams/{$team->id}/members", [
        'user_id' => $outsider->id,
    ]);
    $failRes->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.team_member_must_be_project_member'));

    // Project member can be added
    $addRes = $this->postJson("/api/v1/projects/{$project->id}/teams/{$team->id}/members", [
        'user_id' => $member->id,
        'role_title' => 'Frontend Dev',
    ]);
    $addRes->assertCreated()
        ->assertJsonPath('data.id', $member->id)
        ->assertJsonPath('data.role_title', 'Frontend Dev');

    // Duplicate add is rejected
    $dupRes = $this->postJson("/api/v1/projects/{$project->id}/teams/{$team->id}/members", [
        'user_id' => $member->id,
    ]);
    $dupRes->assertStatus(422);

    // Make member team leader, then remove member from team
    $team->update(['leader_id' => $member->id]);
    expect((int) $team->fresh()->leader_id)->toBe($member->id);

    $removeRes = $this->deleteJson("/api/v1/projects/{$project->id}/teams/{$team->id}/members/{$member->id}");
    $removeRes->assertOk();

    expect($team->hasMember($member))->toBeFalse()
        ->and($team->fresh()->leader_id)->toBeNull();
});

test('team operations reject team IDs belonging to another project', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project1 = Project::factory()->active()->create();
    $project1->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $project2 = Project::factory()->active()->create();
    $team2 = ProjectTeam::factory()->create(['project_id' => $project2->id]);

    $response = $this->patchJson("/api/v1/projects/{$project1->id}/teams/{$team2->id}", [
        'name' => 'Cross Project Exploit',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'team_not_in_project');
});
