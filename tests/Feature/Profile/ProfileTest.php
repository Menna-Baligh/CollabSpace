<?php

use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use App\Enums\TaskStatus;
use App\Enums\UserActivityType;
use App\Enums\UserAvailabilityStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'system_role' => SystemRole::USER,
        'suspended_at' => null,
        'job_title' => 'UI/UX Designer',
        'years_of_experience' => 5,
        'bio' => 'Passionate designer',
        'hours_worked' => 80,
        'availability_status' => UserAvailabilityStatus::AVAILABLE,
    ]);

    $this->otherUser = User::factory()->create([
        'system_role' => SystemRole::USER,
        'suspended_at' => null,
    ]);
});

test('unauthenticated users cannot access profile endpoints', function () {
    $this->getJson(route('api.v1.profile.show'))->assertUnauthorized();
    $this->getJson(route('api.v1.profile.tasks'))->assertUnauthorized();
    $this->getJson(route('api.v1.profile.files'))->assertUnauthorized();
    $this->getJson(route('api.v1.profile.settings.show'))->assertUnauthorized();
    $this->patchJson(route('api.v1.profile.settings.update'), [])->assertUnauthorized();
    $this->postJson(route('api.v1.profile.avatar.store'), [])->assertUnauthorized();
});

test('suspended users are blocked from profile endpoints', function () {
    $suspendedUser = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($suspendedUser, 'sanctum')
        ->getJson(route('api.v1.profile.show'))
        ->assertForbidden();
});

test('profile overview returns correct user information and stats', function () {
    $project = Project::factory()->create();

    // Create 1 completed task assigned to user, and 1 not assigned to user
    $task1 = Task::create([
        'project_id' => $project->id,
        'created_by' => $this->user->id,
        'title' => 'Completed User Task',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
        'position' => 1,
    ]);
    $task1->assignees()->attach($this->user->id);

    $task2 = Task::create([
        'project_id' => $project->id,
        'created_by' => $this->otherUser->id,
        'title' => 'Other Task',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
        'position' => 2,
    ]);
    $task2->assignees()->attach($this->otherUser->id);

    // Create 12 activities to test recent 10 limit
    for ($i = 1; $i <= 12; $i++) {
        UserActivity::create([
            'user_id' => $this->user->id,
            'action_type' => UserActivityType::TASK_UPDATED,
            'description' => "Activity {$i}",
            'created_at' => now()->addMinutes($i),
        ]);
    }

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.profile.show'))
        ->assertOk();

    $response->assertJsonPath('data.name', $this->user->name)
        ->assertJsonPath('data.job_title', 'UI/UX Designer')
        ->assertJsonPath('data.stats.completed_tasks_count', 1)
        ->assertJsonPath('data.stats.hours_worked', 80)
        ->assertJsonPath('data.about.bio', 'Passionate designer');

    $activities = $response->json('data.recent_activities');
    expect(count($activities))->toBe(10)
        ->and($activities[0]['description'])->toBe('Activity 12'); // Newest first
});

test('profile tasks are grouped into todo and done excluding soft deleted and unassigned tasks', function () {
    $project = Project::factory()->create();

    $todoTask = Task::create([
        'project_id' => $project->id,
        'created_by' => $this->user->id,
        'title' => 'In Progress Task',
        'status' => TaskStatus::IN_PROGRESS->value,
        'progress' => 40,
        'position' => 1,
    ]);
    $todoTask->assignees()->attach($this->user->id);

    $doneTask = Task::create([
        'project_id' => $project->id,
        'created_by' => $this->user->id,
        'title' => 'Done Task',
        'status' => TaskStatus::COMPLETED->value,
        'progress' => 100,
        'position' => 2,
    ]);
    $doneTask->assignees()->attach($this->user->id);

    $deletedTask = Task::create([
        'project_id' => $project->id,
        'created_by' => $this->user->id,
        'title' => 'Deleted Task',
        'status' => TaskStatus::TODO->value,
        'progress' => 0,
        'position' => 3,
    ]);
    $deletedTask->assignees()->attach($this->user->id);
    $deletedTask->delete();

    $unassignedTask = Task::create([
        'project_id' => $project->id,
        'created_by' => $this->user->id,
        'title' => 'Unassigned Task',
        'status' => TaskStatus::TODO->value,
        'progress' => 0,
        'position' => 4,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.profile.tasks'))
        ->assertOk();

    $todoData = $response->json('data.todo');
    $doneData = $response->json('data.done');

    expect($todoData['count'])->toBe(1)
        ->and($todoData['data'][0]['id'])->toBe($todoTask->id)
        ->and($doneData['count'])->toBe(1)
        ->and($doneData['data'][0]['id'])->toBe($doneTask->id);
});

test('profile files include files uploaded by user and relevant project files without duplicates', function () {
    $project = Project::factory()->create();
    $project->members()->attach($this->user->id, ['role' => ProjectRole::MEMBER->value]);

    $file1 = ProjectFile::create([
        'project_id' => $project->id,
        'uploaded_by' => $this->user->id,
        'name' => 'Uploaded by user in user project',
        'original_name' => 'f1.pdf',
        'file_path' => 'projects/f1.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 1024,
    ]);

    $file2 = ProjectFile::create([
        'project_id' => $project->id,
        'uploaded_by' => $this->otherUser->id,
        'name' => 'Uploaded by other in user project',
        'original_name' => 'f2.pdf',
        'file_path' => 'projects/f2.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 2048,
    ]);

    $otherProject = Project::factory()->create();
    $file3 = ProjectFile::create([
        'project_id' => $otherProject->id,
        'uploaded_by' => $this->otherUser->id,
        'name' => 'Unrelated project file',
        'original_name' => 'f3.pdf',
        'file_path' => 'projects/f3.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 3072,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.profile.files'))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($file1->id)
        ->and($ids)->toContain($file2->id)
        ->and($ids)->not->toContain($file3->id)
        ->and($response->json('meta.total'))->toBe(2);
});

test('settings returns correct editable data and deterministic team project label', function () {
    $project = Project::factory()->create(['name' => 'CollabSpace Mobile']);
    $team = ProjectTeam::create([
        'project_id' => $project->id,
        'name' => 'UI Team',
    ]);
    $team->members()->attach($this->user->id, [
        'role_title' => 'Designer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('api.v1.profile.settings.show'))
        ->assertOk();

    $response->assertJsonPath('data.full_name', $this->user->name)
        ->assertJsonPath('data.email', $this->user->email)
        ->assertJsonPath('data.current_team_project', 'UI Team — CollabSpace Mobile');
});

test('users can update their own profile settings safely', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('api.v1.profile.settings.update'), [
            'name' => 'Salah Updated',
            'phone' => '+20 111 222 3333',
            'availability_status' => UserAvailabilityStatus::BUSY->value,
            'job_title' => 'Lead UI/UX Designer',
            'years_of_experience' => 7,
            'bio' => 'Updated bio information.',
            'hours_worked' => 9999, // Protected field, should NOT update
            'system_role' => SystemRole::ADMIN->value, // Protected field, should NOT update
        ])
        ->assertOk();

    $response->assertJsonPath('data.full_name', 'Salah Updated')
        ->assertJsonPath('data.phone', '+20 111 222 3333')
        ->assertJsonPath('data.availability_status', UserAvailabilityStatus::BUSY->value);

    $this->user->refresh();
    expect($this->user->name)->toBe('Salah Updated')
        ->and($this->user->hours_worked)->toBe(80) // Unchanged
        ->and($this->user->system_role)->toBe(SystemRole::USER); // Unchanged
});

test('duplicate email addresses are rejected but user current email is accepted', function () {
    // Current email accepted
    $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('api.v1.profile.settings.update'), [
            'email' => $this->user->email,
        ])
        ->assertOk();

    // Duplicate email rejected
    $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('api.v1.profile.settings.update'), [
            'email' => $this->otherUser->email,
        ])
        ->assertUnprocessable();
});

test('avatar upload succeeds, deletes old avatar safely, and validates constraints', function () {
    Storage::fake('public');

    // 1. Invalid image type
    $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $this->actingAs($this->user, 'sanctum')
        ->postJson(route('api.v1.profile.avatar.store'), [
            'avatar' => $invalidFile,
        ])
        ->assertUnprocessable();

    // 2. Oversized image (> 5MB)
    $oversizedFile = UploadedFile::fake()->image('large.png')->size(6000);
    $this->actingAs($this->user, 'sanctum')
        ->postJson(route('api.v1.profile.avatar.store'), [
            'avatar' => $oversizedFile,
        ])
        ->assertUnprocessable();

    // 3. Valid image upload
    $validImage1 = UploadedFile::fake()->image('avatar1.jpg', 200, 200);
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('api.v1.profile.avatar.store'), [
            'avatar' => $validImage1,
        ])
        ->assertOk();

    $this->user->refresh();
    $firstPath = $this->user->avatar;
    expect($firstPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstPath);

    // 4. Upload replacement avatar -> deletes old avatar
    $validImage2 = UploadedFile::fake()->image('avatar2.png', 200, 200);
    $this->actingAs($this->user, 'sanctum')
        ->postJson(route('api.v1.profile.avatar.store'), [
            'avatar' => $validImage2,
        ])
        ->assertOk();

    $this->user->refresh();
    $secondPath = $this->user->avatar;
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertExists($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
});
