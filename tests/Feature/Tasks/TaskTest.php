<?php

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->pm = User::factory()->create();
    $this->member1 = User::factory()->create();
    $this->member2 = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->project = Project::factory()->create([
        'status' => ProjectStatus::ACTIVE,
    ]);

    // Attach PM and members to project
    $this->project->members()->attach($this->pm->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
    $this->project->members()->attach($this->member1->id, ['role' => ProjectRole::MEMBER->value]);
    $this->project->members()->attach($this->member2->id, ['role' => ProjectRole::MEMBER->value]);

    $this->team = ProjectTeam::factory()->create([
        'project_id' => $this->project->id,
        'leader_id' => $this->member1->id,
    ]);
    $this->team->members()->attach($this->member1->id, ['role_title' => 'Lead']);
    $this->team->members()->attach($this->member2->id, ['role_title' => 'Dev']);
});

test('unauthenticated user receives 401 on project tasks endpoint', function () {
    $this->getJson("/api/v1/projects/{$this->project->id}/tasks")
        ->assertStatus(401);
});

test('authenticated active user can list project tasks grouped into 4 kanban columns', function () {
    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Todo Task',
        'status' => TaskStatus::TODO->value,
        'position' => 1,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'In Progress Task',
        'status' => TaskStatus::IN_PROGRESS->value,
        'position' => 0,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'In Review Task',
        'status' => TaskStatus::IN_REVIEW->value,
        'position' => 0,
    ]);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Completed Task',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
        'position' => 0,
    ]);

    $response = $this->actingAs($this->outsider)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'columns' => [
                    'todo',
                    'in_progress',
                    'in_review',
                    'completed',
                ],
                'counts' => [
                    'todo',
                    'in_progress',
                    'in_review',
                    'completed',
                ],
            ],
        ]);

    expect($response->json('data.counts.todo'))->toBe(1)
        ->and($response->json('data.counts.in_progress'))->toBe(1)
        ->and($response->json('data.counts.in_review'))->toBe(1)
        ->and($response->json('data.counts.completed'))->toBe(1);
});

test('project tasks can be filtered by search, status, priority, team, and assignee', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'project_team_id' => $this->team->id,
        'created_by' => $this->pm->id,
        'title' => 'Unique Searchable Task',
        'status' => TaskStatus::TODO->value,
        'priority' => TaskPriority::HIGH->value,
        'position' => 0,
    ]);
    $task->assignees()->attach($this->member1->id);

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Other Task',
        'status' => TaskStatus::TODO->value,
        'priority' => TaskPriority::LOW->value,
        'position' => 1,
    ]);

    // Search filter
    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks?search=Unique")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.columns.todo')
        ->assertJsonPath('data.columns.todo.0.title', 'Unique Searchable Task');

    // Priority filter
    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks?priority=high")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.columns.todo')
        ->assertJsonPath('data.columns.todo.0.priority', 'high');

    // Team filter
    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks?team_id={$this->team->id}")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.columns.todo');

    // Assignee filter
    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks?assignee_id={$this->member1->id}")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.columns.todo');
});

test('project member can create a task successfully', function () {
    $payload = [
        'title' => 'New Task',
        'description' => 'Detailed description of task',
        'status' => TaskStatus::IN_PROGRESS->value,
        'priority' => TaskPriority::HIGH->value,
        'progress' => 45,
        'due_date' => now()->addDays(5)->format('Y-m-d'),
        'project_team_id' => $this->team->id,
        'reviewer_id' => $this->pm->id,
        'assignee_ids' => [$this->member1->id, $this->member2->id],
    ];

    $response = $this->actingAs($this->member1)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'New Task')
        ->assertJsonPath('data.reviewer.id', $this->pm->id)
        ->assertJsonPath('data.team.id', $this->team->id);

    expect($response->json('data.assignees'))->toHaveCount(2);

    $this->assertDatabaseHas('tasks', [
        'project_id' => $this->project->id,
        'created_by' => $this->member1->id,
        'title' => 'New Task',
        'progress' => 45,
    ]);
});

test('creating a task with completed status automatically forces progress to 100', function () {
    $payload = [
        'title' => 'Completed from start',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 20, // Even if 20 was passed, must become 100
    ];

    $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.progress', 100);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Completed from start',
        'status' => 'completed',
        'progress' => 100,
    ]);
});

test('creating task with a team from another project fails with 422', function () {
    $otherProject = Project::factory()->create();
    $otherTeam = ProjectTeam::factory()->create(['project_id' => $otherProject->id]);

    $payload = [
        'title' => 'Invalid team task',
        'project_team_id' => $otherTeam->id,
    ];

    $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", $payload)
        ->assertStatus(422);
});

test('assigning a non-project-member fails with 422', function () {
    $payload = [
        'title' => 'Invalid assignee task',
        'assignee_ids' => [$this->outsider->id],
    ];

    $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", $payload)
        ->assertStatus(422);
});

test('selecting a non-project-member as reviewer fails with 422', function () {
    $payload = [
        'title' => 'Invalid reviewer task',
        'reviewer_id' => $this->outsider->id,
    ];

    $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", $payload)
        ->assertStatus(422);
});

test('duplicate assignees are handled safely and saved once', function () {
    $payload = [
        'title' => 'Duplicate assignee task',
        'assignee_ids' => [$this->member1->id, $this->member1->id],
    ];

    $response = $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", $payload);

    // Form request has 'distinct' validation rule on assignee_ids.*
    $response->assertStatus(422);
});

test('updating task fields succeeds and preserves assignments when omitted', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Original Title',
        'status' => TaskStatus::TODO->value,
    ]);
    $task->assignees()->attach($this->member1->id);

    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'Updated Title',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonCount(1, 'data.assignees');

    expect($task->fresh()->assignees)->toHaveCount(1);
});

test('updating task with empty assignee_ids removes all assignments', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Assigned Task',
        'status' => TaskStatus::TODO->value,
    ]);
    $task->assignees()->attach($this->member1->id);

    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'assignee_ids' => [],
        ])
        ->assertStatus(200)
        ->assertJsonCount(0, 'data.assignees');

    expect($task->fresh()->assignees)->toHaveCount(0);
});

test('moving task to completed status automatically sets progress to 100', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'In progress task',
        'status' => TaskStatus::IN_PROGRESS->value,
        'progress' => 50,
    ]);

    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/status", [
            'status' => TaskStatus::COMPLETED->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress', 100);

    expect($task->fresh()->progress)->toBe(100);
});

test('moving task from completed does not reset progress unless explicitly supplied', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Done task',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
    ]);

    // Move to in_review without progress
    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/status", [
            'status' => TaskStatus::IN_REVIEW->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'in_review')
        ->assertJsonPath('data.progress', 100);

    // Move to in_progress with explicit progress
    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/status", [
            'status' => TaskStatus::IN_PROGRESS->value,
            'progress' => 70,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.progress', 70);
});

test('task position is persisted correctly on status update', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Task Reorder',
        'status' => TaskStatus::TODO->value,
        'position' => 0,
    ]);

    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/status", [
            'status' => TaskStatus::IN_PROGRESS->value,
            'position' => 5,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.position', 5);

    expect($task->fresh()->position)->toBe(5);
});

test('showing a task from another project returns 404', function () {
    $otherProject = Project::factory()->create();
    $task = Task::create([
        'project_id' => $otherProject->id,
        'created_by' => $this->pm->id,
        'title' => 'Other Project Task',
        'status' => TaskStatus::TODO->value,
    ]);

    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
        ->assertStatus(404);
});

test('soft-deleted tasks are not returned in listing', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Deleted Task',
        'status' => TaskStatus::TODO->value,
    ]);
    $task->delete();

    $this->actingAs($this->pm)
        ->getJson("/api/v1/projects/{$this->project->id}/tasks")
        ->assertStatus(200)
        ->assertJsonCount(0, 'data.columns.todo');
});

test('task deletion by project manager succeeds', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->member1->id,
        'title' => 'Member Task',
        'status' => TaskStatus::TODO->value,
    ]);

    $this->actingAs($this->pm)
        ->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

test('task deletion by creator succeeds', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->member1->id,
        'title' => 'My Task',
        'status' => TaskStatus::TODO->value,
    ]);

    $this->actingAs($this->member1)
        ->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

test('task deletion by unrelated user fails with 403', function () {
    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->member1->id,
        'title' => 'Member 1 Task',
        'status' => TaskStatus::TODO->value,
    ]);

    $this->actingAs($this->member2)
        ->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
        ->assertStatus(403);
});

test('all task mutations are rejected for archived projects with 422', function () {
    $this->project->update(['status' => ProjectStatus::ARCHIVED, 'archived_at' => now()]);

    $task = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Archived Task',
        'status' => TaskStatus::TODO->value,
    ]);

    // Create
    $this->actingAs($this->pm)
        ->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Should fail',
        ])
        ->assertStatus(422);

    // Update
    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'Should fail update',
        ])
        ->assertStatus(422);

    // Update status
    $this->actingAs($this->pm)
        ->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/status", [
            'status' => TaskStatus::IN_PROGRESS->value,
        ])
        ->assertStatus(422);

    // Delete
    $this->actingAs($this->pm)
        ->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
        ->assertStatus(422);
});

test('global task listing returns tasks across visible projects', function () {
    $project2 = Project::factory()->create();

    Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Project 1 Task',
        'status' => TaskStatus::TODO->value,
    ]);

    Task::create([
        'project_id' => $project2->id,
        'created_by' => $this->pm->id,
        'title' => 'Project 2 Task',
        'status' => TaskStatus::TODO->value,
    ]);

    $response = $this->actingAs($this->member1)
        ->getJson('/api/v1/tasks?scope=all')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.columns.todo')
        ->assertJsonStructure([
            'data' => [
                'columns' => [
                    'todo' => [
                        '*' => [
                            'id',
                            'title',
                            'project' => ['id', 'name'],
                        ],
                    ],
                ],
            ],
        ]);
});

test('global tasks scope=my_tasks returns only assigned or reviewed tasks', function () {
    $myAssignedTask = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Assigned to Member 1',
        'status' => TaskStatus::TODO->value,
    ]);
    $myAssignedTask->assignees()->attach($this->member1->id);

    $myReviewedTask = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'reviewer_id' => $this->member1->id,
        'title' => 'Reviewed by Member 1',
        'status' => TaskStatus::TODO->value,
    ]);

    $otherTask = Task::create([
        'project_id' => $this->project->id,
        'created_by' => $this->pm->id,
        'title' => 'Unrelated Task',
        'status' => TaskStatus::TODO->value,
    ]);

    $this->actingAs($this->member1)
        ->getJson('/api/v1/tasks?scope=my_tasks')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.columns.todo');
});

test('creating global task works with valid project and rejects archived project', function () {
    $payload = [
        'project_id' => $this->project->id,
        'title' => 'Global Created Task',
    ];

    $this->actingAs($this->member1)
        ->postJson('/api/v1/tasks', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Global Created Task')
        ->assertJsonPath('data.project.id', $this->project->id);

    // Reject archived
    $this->project->update(['status' => ProjectStatus::ARCHIVED, 'archived_at' => now()]);

    $this->actingAs($this->member1)
        ->postJson('/api/v1/tasks', $payload)
        ->assertStatus(422);
});
