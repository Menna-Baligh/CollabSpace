<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('authenticated user can create a project and automatically becomes the first project manager', function () {
    $creator = User::factory()->create();
    Sanctum::actingAs($creator);

    $payload = [
        'name' => 'CollabSpace Backend 2.0',
        'description' => 'Scalable modular RESTful API architecture.',
        'project_type' => 'Backend Development',
        'start_date' => now()->format('Y-m-d'),
        'deadline' => now()->addMonth()->format('Y-m-d'),
        'priority' => 'high',
        'color' => '#10B981',
        'icon' => 'server',
    ];

    $response = $this->postJson('/api/v1/projects', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'CollabSpace Backend 2.0')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.status', 'active');

    $projectId = $response->json('data.id');
    $project = Project::find($projectId);

    expect($project)->not->toBeNull()
        ->and($project->hasProjectManager($creator))->toBeTrue();
});

test('project creation validates required fields and date ordering', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Missing name
    $response = $this->postJson('/api/v1/projects', [
        'start_date' => now()->format('Y-m-d'),
    ]);
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['details' => ['name']]]);

    // Deadline before start date
    $dateResponse = $this->postJson('/api/v1/projects', [
        'name' => 'Bad Dates Project',
        'start_date' => '2026-12-01',
        'deadline' => '2026-11-01',
    ]);
    $dateResponse->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['deadline']]]);

    // Deadline with null start_date succeeds
    $nullStartDateResponse = $this->postJson('/api/v1/projects', [
        'name' => 'Null Start Date Project',
        'start_date' => null,
        'deadline' => '2026-12-25',
    ]);
    $nullStartDateResponse->assertCreated()
        ->assertJsonPath('data.deadline', '2026-12-25')
        ->assertJsonPath('data.start_date', null);
});

test('non-project-manager cannot update a project', function () {
    $pm = User::factory()->create();
    $nonPm = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $project = Project::factory()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($nonPm->id, ['role' => ProjectRole::MEMBER->value]);

    // Regular member attempt
    Sanctum::actingAs($nonPm);
    $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hacked Name'])
        ->assertStatus(403);

    // System administrator who is NOT a project manager also receives 403
    Sanctum::actingAs($admin);
    $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Admin Hacked Name'])
        ->assertStatus(403);
});

test('project manager can update project details', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->create(['name' => 'Original Name']);
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->patchJson("/api/v1/projects/{$project->id}", [
        'name' => 'Updated Name',
        'description' => 'Updated Description',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.description', 'Updated Description');
});

test('mandatory requirement: project status can only update between active and done, never archived via update endpoint', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    // 1. Update from active to done succeeds
    $doneResponse = $this->patchJson("/api/v1/projects/{$project->id}", ['status' => 'done']);
    $doneResponse->assertOk()
        ->assertJsonPath('data.status', 'done');

    // 2. Update from done to active succeeds
    $activeResponse = $this->patchJson("/api/v1/projects/{$project->id}", ['status' => 'active']);
    $activeResponse->assertOk()
        ->assertJsonPath('data.status', 'active');

    // 3. Updating to archived via regular update endpoint is REJECTED with 422
    $archivedResponse = $this->patchJson("/api/v1/projects/{$project->id}", ['status' => 'archived']);
    $archivedResponse->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

test('archived project cannot be updated through regular update endpoint', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->archived()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->patchJson("/api/v1/projects/{$project->id}", [
        'name' => 'Cannot Edit Archived',
    ]);

    $response->assertStatus(422);
});

test('only project manager can delete a project which performs a soft delete', function () {
    $pm = User::factory()->create();
    $nonPm = User::factory()->create();

    $project = Project::factory()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    // Non-PM cannot delete
    Sanctum::actingAs($nonPm);
    $this->deleteJson("/api/v1/projects/{$project->id}")->assertStatus(403);

    // PM can delete
    Sanctum::actingAs($pm);
    $response = $this->deleteJson("/api/v1/projects/{$project->id}");
    $response->assertOk()
        ->assertJsonPath('meta.message', __('projects.deleted_successfully'));

    // Check soft delete: project exists in withTrashed() but not in standard query
    expect(Project::find($project->id))->toBeNull()
        ->and(Project::withTrashed()->find($project->id))->not->toBeNull();
});
