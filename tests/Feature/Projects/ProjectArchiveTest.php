<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('project manager can archive a project, setting status to archived and archived_at to now', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->patchJson("/api/v1/projects/{$project->id}/archive");

    $response->assertOk()
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('meta.message', __('projects.archived_successfully'));

    $project->refresh();
    expect($project->status)->toBe(ProjectStatus::ARCHIVED)
        ->and($project->archived_at)->not->toBeNull();
});

test('project manager can unarchive an archived project, restoring active status and clearing archived_at', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $project = Project::factory()->archived()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->patchJson("/api/v1/projects/{$project->id}/unarchive");

    $response->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('meta.message', __('projects.unarchived_successfully'));

    $project->refresh();
    expect($project->status)->toBe(ProjectStatus::ACTIVE)
        ->and($project->archived_at)->toBeNull();
});

test('repeated archive calls preserve original archived_at timestamp and load members', function () {
    $pm = User::factory()->create();
    Sanctum::actingAs($pm);

    $originalArchivedAt = now()->subDays(3)->startOfSecond();
    $project = Project::factory()->create([
        'status' => ProjectStatus::ARCHIVED,
        'archived_at' => $originalArchivedAt,
    ]);
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $response = $this->patchJson("/api/v1/projects/{$project->id}/archive");

    $response->assertOk()
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonStructure(['data' => ['members']]);

    $project->refresh();
    // Timestamp must NOT have changed to now()
    expect($project->archived_at->toIso8601String())->toBe($originalArchivedAt->toIso8601String());
});

test('non-project-manager cannot archive or unarchive a project', function () {
    $pm = User::factory()->create();
    $nonPm = User::factory()->create();

    $project = Project::factory()->active()->create();
    $project->members()->attach($pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $project->members()->attach($nonPm->id, ['role' => ProjectRole::MEMBER->value]);

    Sanctum::actingAs($nonPm);

    $this->patchJson("/api/v1/projects/{$project->id}/archive")->assertStatus(403);
    $this->patchJson("/api/v1/projects/{$project->id}/unarchive")->assertStatus(403);
});
