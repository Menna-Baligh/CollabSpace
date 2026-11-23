<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pm = User::factory()->create();
    $this->member1 = User::factory()->create();
    $this->member2 = User::factory()->create();

    $this->project = Project::factory()->create([
        'status' => ProjectStatus::ACTIVE,
    ]);

    $this->project->members()->attach($this->pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $this->project->members()->attach($this->member1->id, ['role' => ProjectRole::MEMBER->value]);
    $this->project->members()->attach($this->member2->id, ['role' => ProjectRole::MEMBER->value]);

    $this->team1 = ProjectTeam::factory()->create([
        'project_id' => $this->project->id,
        'leader_id' => $this->member1->id,
    ]);
    $this->team1->members()->attach($this->member1->id, ['role_title' => 'Lead']);
    $this->team1->members()->attach($this->member2->id, ['role_title' => 'Dev']);
});

test('project list files_count reflects real non-deleted files', function () {
    $file1 = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->pm->id,
        'name' => 'File 1',
        'original_name' => 'f1.pdf',
        'file_path' => 'p/f1.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $file2 = ProjectFile::create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->pm->id,
        'name' => 'File 2',
        'original_name' => 'f2.pdf',
        'file_path' => 'p/f2.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
    ]);

    $file2->delete(); // Soft delete

    $response = $this->actingAs($this->pm)
        ->getJson('/api/v1/projects')
        ->assertStatus(200);

    $projectItem = collect($response->json('data'))->firstWhere('id', $this->project->id);
    expect($projectItem['files_count'])->toBe(1);
});

test('project overview statistics calculate real task statistics and completion percentage', function () {
    // 1 Todo, 1 In Progress, 1 In Review, 2 Completed = 5 Total
    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'T1',
        'status' => TaskStatus::TODO->value,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'T2',
        'status' => TaskStatus::IN_PROGRESS->value,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'T3',
        'status' => TaskStatus::IN_REVIEW->value,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'T4',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'T5',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
    ]);

    // Soft-deleted task should not be counted
    $deletedTask = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'T_Del',
        'status' => TaskStatus::COMPLETED->value,
    ]);
    $deletedTask->delete();

    $response = $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.total_tasks', 5)
        ->assertJsonPath('data.total_tasks_count', 5)
        ->assertJsonPath('data.pending_tasks', 1)
        ->assertJsonPath('data.in_progress_tasks', 2) // 1 in_progress + 1 in_review
        ->assertJsonPath('data.completed_tasks', 2)
        ->assertJsonPath('data.completion_percentage', 40); // 2 / 5 * 100 = 40%
});

test('project with zero tasks returns 0 completion percentage', function () {
    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.total_tasks', 0)
        ->assertJsonPath('data.completion_percentage', 0);
});

test('project teams calculate real task statistics for teams and members', function () {
    // Team 1 has 3 tasks: 2 completed, 1 in_progress
    $t1 = Task::create([
        'project_id' => $this->project->id,
        'project_team_id' => $this->team1->id,
        'created_by' => $this->pm->id,
        'title' => 'Team Task 1',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
    ]);
    $t1->assignees()->attach($this->member1->id);

    $t2 = Task::create([
        'project_id' => $this->project->id,
        'project_team_id' => $this->team1->id,
        'created_by' => $this->pm->id,
        'title' => 'Team Task 2',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
    ]);
    $t2->assignees()->attach($this->member1->id);
    $t2->assignees()->attach($this->member2->id);

    $t3 = Task::create([
        'project_id' => $this->project->id,
        'project_team_id' => $this->team1->id,
        'created_by' => $this->pm->id,
        'title' => 'Team Task 3',
        'status' => TaskStatus::IN_PROGRESS->value,
        'progress' => 50,
    ]);
    $t3->assignees()->attach($this->member2->id);

    // Another task in another team
    $team2 = ProjectTeam::factory()->create(['project_id' => $this->project->id]);
    $team2->members()->attach($this->member1->id);

    $otherTeamTask = Task::create([
        'project_id' => $this->project->id,
        'project_team_id' => $team2->id,
        'created_by' => $this->pm->id,
        'title' => 'Team 2 Task',
        'status' => TaskStatus::COMPLETED->value,
    ]);
    $otherTeamTask->assignees()->attach($this->member1->id);

    $response = $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/teams")
        ->assertStatus(200);

    $team1Data = collect($response->json('data'))->firstWhere('id', $this->team1->id);

    // Team 1 completion percentage: 2 / 3 = 67%
    expect($team1Data['completion_percentage'])->toBe(67);

    // Member 1 metrics in Team 1: assigned 2 (t1, t2), completed 2
    $member1Data = collect($team1Data['members'])->firstWhere('id', $this->member1->id);
    expect($member1Data['assigned_tasks_count'])->toBe(2)
        ->and($member1Data['completed_tasks_count'])->toBe(2);

    // Member 2 metrics in Team 1: assigned 2 (t2, t3), completed 1 (t2)
    $member2Data = collect($team1Data['members'])->firstWhere('id', $this->member2->id);
    expect($member2Data['assigned_tasks_count'])->toBe(2)
        ->and($member2Data['completed_tasks_count'])->toBe(1);
});
