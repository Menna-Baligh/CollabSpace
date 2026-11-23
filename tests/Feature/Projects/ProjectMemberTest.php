<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('project manager can add a member to the project', function () {
    $pm = User::factory()->create();
    $newMember = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->postJson(route('api.v1.projects.members.store', $project), [
        'user_id' => $newMember->id,
        'role' => 'member',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.id', $newMember->id)
        ->assertJsonPath('data.role', 'member');

    expect($project->isMemberOfProject($newMember))->toBeTrue();
});

test('project manager can add multiple project managers to the project', function () {
    $pm1 = User::factory()->create();
    $pm2 = User::factory()->create();
    Sanctum::actingAs($pm1);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm1->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->postJson(route('api.v1.projects.members.store', $project), [
        'user_id' => $pm2->id,
        'role' => 'project_manager',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.role', 'project_manager');

    expect($project->hasProjectManager($pm2))->toBeTrue();
});

test('adding duplicate member to project is rejected', function () {
    $pm = User::factory()->create();
    $member = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($member->id, ['role' => ProjectRole::MEMBER->value]);

    $response = $this->postJson(route('api.v1.projects.members.store', $project), [
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.member_already_exists'));
});

test('cannot add a suspended user to a project', function () {
    $pm = User::factory()->create();
    $suspendedUser = User::factory()->suspended()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->postJson(route('api.v1.projects.members.store', $project), [
        'user_id' => $suspendedUser->id,
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.cannot_add_suspended_user'));
});

test('project manager can promote member and demote when multiple PMs exist', function () {
    $pm1 = User::factory()->create();
    $pm2 = User::factory()->create();
    $member = User::factory()->create();
    Sanctum::actingAs($pm1);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm1->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($pm2->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($member->id, ['role' => ProjectRole::MEMBER->value]);

    // Promote member to PM
    $promoteRes = $this->patchJson(route('api.v1.projects.members.update', [$project, $member]), [
        'role' => 'project_manager',
    ]);
    $promoteRes->assertOk()->assertJsonPath('data.role', 'project_manager');

    // Demote pm2 to member (allowed because pm1 and member are still active PMs)
    $demoteRes = $this->patchJson(route('api.v1.projects.members.update', [$project, $pm2]), [
        'role' => 'member',
    ]);
    $demoteRes->assertOk()->assertJsonPath('data.role', 'member');
});

test('last project manager cannot be demoted', function () {
    $solePm = User::factory()->create();
    Sanctum::actingAs($solePm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->patchJson(route('api.v1.projects.members.update', [$project, $solePm]), [
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.cannot_demote_last_project_manager'));
});

test('last project manager cannot be removed from project', function () {
    $solePm = User::factory()->create();
    Sanctum::actingAs($solePm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($solePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->deleteJson(route('api.v1.projects.members.destroy', [$project, $solePm]));

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.cannot_remove_last_project_manager'));
});

test('removing a project member removes team memberships and clears team leader assignment', function () {
    $pm = User::factory()->create();
    $member = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($member->id, ['role' => ProjectRole::MEMBER->value]);

    $team = ProjectTeam::factory()->create([
        'project_id' => $project->id,
        'leader_id' => $member->id,
    ]);
    $team->members()->attach($member->id, ['role_title' => 'Tech Lead']);

    expect($team->hasMember($member))->toBeTrue()
        ->and((int) $team->fresh()->leader_id)->toBe($member->id);

    $response = $this->deleteJson(route('api.v1.projects.members.destroy', [$project, $member]));
    $response->assertOk();

    // Check project membership deleted
    expect($project->isMemberOfProject($member))->toBeFalse();

    // Check team membership deleted
    expect($team->hasMember($member))->toBeFalse();

    // Check team leader cleared to null
    expect($team->fresh()->leader_id)->toBeNull();
});

test('member management operations are blocked for archived projects', function () {
    $pm = User::factory()->create();
    $user = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->archived()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->postJson(route('api.v1.projects.members.store', $project), [
        'user_id' => $user->id,
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.archived_modification_prohibited'));
});

test('suspended project manager can be removed when an active PM remains', function () {
    $activePm = User::factory()->create();
    $suspendedPm = User::factory()->suspended()->create();
    Sanctum::actingAs($activePm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($activePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($suspendedPm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    // Removing the suspended PM should succeed because activePm remains
    $response = $this->deleteJson(route('api.v1.projects.members.destroy', [$project, $suspendedPm]));

    $response->assertOk();
    expect($project->isMemberOfProject($suspendedPm))->toBeFalse();
});

test('suspended project manager cannot be demoted when active PM count would drop to zero', function () {
    // Edge case: only one active PM exists; demoting them must be blocked
    $activePm = User::factory()->create();
    $member = User::factory()->create();
    Sanctum::actingAs($activePm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($activePm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($member->id, ['role' => ProjectRole::MEMBER->value]);

    // Demoting the only active PM should be rejected
    $response = $this->patchJson(route('api.v1.projects.members.update', [$project, $activePm]), [
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.message', __('projects.cannot_demote_last_project_manager'));
});
