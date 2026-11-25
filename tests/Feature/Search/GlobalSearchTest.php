<?php

use App\Enums\MeetingStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects unauthenticated search requests', function () {
    $this->getJson('/api/v1/search?q=alpha')->assertUnauthorized();
});

it('validates search query parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/search')
        ->assertUnprocessable();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/search?q=a')
        ->assertUnprocessable();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/search?q='.str_repeat('a', 101))
        ->assertUnprocessable();
});

it('returns authorized results across categories and excludes soft-deleted and unauthorized records', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $accessibleProject = Project::factory()->create(['name' => 'Alpha Launch', 'status' => ProjectStatus::ACTIVE]);
    $accessibleProject->members()->attach($user->id, ['role' => ProjectRole::MEMBER->value]);

    $privateProject = Project::factory()->create(['name' => 'Alpha Secret', 'status' => ProjectStatus::ACTIVE]);
    $privateProject->members()->attach($otherUser->id, ['role' => ProjectRole::MEMBER->value]);

    $deletedProject = Project::factory()->create(['name' => 'Alpha Deleted', 'status' => ProjectStatus::ACTIVE]);
    $deletedProject->members()->attach($user->id, ['role' => ProjectRole::MEMBER->value]);
    $deletedProject->delete();

    Task::create(['project_id' => $accessibleProject->id, 'created_by' => $user->id, 'title' => 'Alpha Task']);
    ProjectFile::create([
        'project_id' => $accessibleProject->id,
        'uploaded_by' => $user->id,
        'name' => 'Alpha Spec.pdf',
        'original_name' => 'Alpha Spec.pdf',
        'file_path' => 'projects/1/alpha.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 1024,
    ]);
    Meeting::create([
        'creator_id' => $user->id,
        'title' => 'Alpha Kickoff',
        'status' => MeetingStatus::SCHEDULED,
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
        'timezone' => 'UTC',
        'provider' => 'jitsi',
        'room_name' => 'alpha-kickoff',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/search?q=Alpha')
        ->assertOk();

    expect(collect($response->json('data.projects'))->pluck('title')->all())->toBe(['Alpha Launch']);
    expect(collect($response->json('data.tasks'))->pluck('title')->all())->toBe(['Alpha Task']);
    expect(collect($response->json('data.files'))->pluck('title')->all())->toBe(['Alpha Spec.pdf']);
    expect(collect($response->json('data.meetings'))->pluck('title')->all())->toBe(['Alpha Kickoff']);
});

it('limits each category to 5 results', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['name' => 'Beta Project', 'status' => ProjectStatus::ACTIVE]);
    $project->members()->attach($user->id, ['role' => ProjectRole::MEMBER->value]);

    for ($i = 1; $i <= 8; $i++) {
        Task::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'title' => "Beta Task Item {$i}",
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/search?q=Beta')
        ->assertOk();

    expect(count($response->json('data.tasks')))->toBe(5);
});
