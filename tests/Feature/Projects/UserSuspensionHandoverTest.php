<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('mandatory requirement: only system administrators can suspend users (regular user and PM receive 403)', function () {
    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();
    $pm = User::factory()->create();
    $targetUser = User::factory()->create();

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    // 1. Unauthenticated -> 401
    $this->postJson("/api/v1/users/{$targetUser->id}/suspend")->assertStatus(401);

    // 2. Regular user -> 403
    Sanctum::actingAs($regularUser);
    $this->postJson("/api/v1/users/{$targetUser->id}/suspend")->assertStatus(403);

    // 3. Project manager who is not admin -> 403
    Sanctum::actingAs($pm);
    $this->postJson("/api/v1/users/{$targetUser->id}/suspend")->assertStatus(403);

    // 4. System administrator -> 200
    Sanctum::actingAs($admin);
    $response = $this->postJson("/api/v1/users/{$targetUser->id}/suspend");
    $response->assertOk()
        ->assertJsonPath('meta.message', __('projects.user_suspended_successfully'));

    expect($targetUser->fresh()->isSuspended())->toBeTrue();
});

test('suspending normal user revokes all sanctum tokens', function () {
    $admin = User::factory()->admin()->create();
    $targetUser = User::factory()->create();

    // Generate tokens for target user
    $token1 = $targetUser->createToken('device1')->plainTextToken;
    $token2 = $targetUser->createToken('device2')->plainTextToken;
    expect($targetUser->tokens()->count())->toBe(2);

    Sanctum::actingAs($admin);
    $response = $this->postJson("/api/v1/users/{$targetUser->id}/suspend");
    $response->assertOk();

    // Check tokens revoked
    expect($targetUser->fresh()->tokens()->count())->toBe(0);

    // Attempting to access protected endpoint with old token is rejected
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$token1)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

test('suspending sole project manager without handovers returns 422 with list of projects', function () {
    $admin = User::factory()->admin()->create();
    $solePm = User::factory()->create();

    $project1 = Project::factory()->active()->create(['name' => 'Project Alpha']);
    $project1->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $project2 = Project::factory()->active()->create(['name' => 'Project Beta']);
    $project2->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    Sanctum::actingAs($admin);
    $response = $this->postJson("/api/v1/users/{$solePm->id}/suspend");

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'handover_required')
        ->assertJsonStructure([
            'error' => [
                'details' => [
                    'projects' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ],
        ]);

    $projectsList = $response->json('error.details.projects');
    expect($projectsList)->toHaveCount(2);

    // Ensure target user was NOT suspended
    expect($solePm->fresh()->isSuspended())->toBeFalse();
});

test('handover promotes replacement to project manager, demotes suspended user, and suspends atomically', function () {
    $admin = User::factory()->admin()->create();
    $solePm = User::factory()->create();
    $replacement = User::factory()->create();

    $project = Project::factory()->active()->create(['name' => 'Mission Critical App']);
    $project->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($replacement->id, ['role' => ProjectRole::MEMBER->value]);

    Sanctum::actingAs($admin);
    $response = $this->postJson("/api/v1/users/{$solePm->id}/suspend", [
        'handovers' => [
            [
                'project_id' => $project->id,
                'replacement_user_id' => $replacement->id,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('projects.user_suspended_successfully'));

    // Check solePm is now demoted to member and suspended
    $solePmRole = $project->members()->where('users.id', $solePm->id)->first()->pivot->role;
    expect($solePmRole)->toBe(ProjectRole::MEMBER->value)
        ->and($solePm->fresh()->isSuspended())->toBeTrue();

    // Check replacement is now project manager
    $replacementRole = $project->members()->where('users.id', $replacement->id)->first()->pivot->role;
    expect($replacementRole)->toBe(ProjectRole::PROJECT_MANAGER->value)
        ->and($project->activeProjectManagersCount())->toBe(1);
});

test('handover fails and rolls back completely if replacement user is suspended', function () {
    $admin = User::factory()->admin()->create();
    $solePm = User::factory()->create();
    $suspendedReplacement = User::factory()->suspended()->create();

    $project = Project::factory()->active()->create();
    $project->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    Sanctum::actingAs($admin);
    $response = $this->postJson("/api/v1/users/{$solePm->id}/suspend", [
        'handovers' => [
            [
                'project_id' => $project->id,
                'replacement_user_id' => $suspendedReplacement->id,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.replacement_is_suspended'));

    // Nothing was changed
    expect($solePm->fresh()->isSuspended())->toBeFalse()
        ->and($project->hasProjectManager($solePm))->toBeTrue();
});

test('handover rejects duplicate or unexpected project IDs', function () {
    $admin = User::factory()->admin()->create();
    $solePm = User::factory()->create();
    $replacement = User::factory()->create();

    $project = Project::factory()->active()->create();
    $project->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    Sanctum::actingAs($admin);

    // Duplicate project in handovers
    $dupResponse = $this->postJson("/api/v1/users/{$solePm->id}/suspend", [
        'handovers' => [
            ['project_id' => $project->id, 'replacement_user_id' => $replacement->id],
            ['project_id' => $project->id, 'replacement_user_id' => $replacement->id],
        ],
    ]);
    $dupResponse->assertStatus(422);

    // Unexpected project ID
    $otherProject = Project::factory()->active()->create();
    $mismatchResponse = $this->postJson("/api/v1/users/{$solePm->id}/suspend", [
        'handovers' => [
            ['project_id' => $otherProject->id, 'replacement_user_id' => $replacement->id],
        ],
    ]);
    $mismatchResponse->assertStatus(422);
});
