<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Files\ProjectFileUploadedNotification;
use App\Notifications\Meetings\MeetingScheduledNotification;
use App\Notifications\Projects\ProjectMemberAddedNotification;
use App\Notifications\Tasks\TaskAssignedNotification;
use App\Services\MeetingService;
use App\Services\ProjectFileService;
use App\Services\ProjectMemberService;
use App\Services\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('dispatches domain notifications for project, task, file, and meeting actions', function () {
    Notification::fake();
    Storage::fake('public');

    $actor = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create();
    $project->members()->attach($actor->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);

    $this->actingAs($actor);

    // 1. Project member added
    app(ProjectMemberService::class)->addMember($project, $member->id, ProjectRole::MEMBER->value);
    Notification::assertSentTo($member, ProjectMemberAddedNotification::class);

    // 2. Task assigned
    app(TaskService::class)->createTask($project, $actor, [
        'title' => 'New Task',
        'assignee_ids' => [$member->id],
    ]);
    Notification::assertSentTo($member, TaskAssignedNotification::class);

    // 3. File uploaded
    $file = UploadedFile::fake()->create('spec.pdf', 100);
    app(ProjectFileService::class)->uploadFile($project, $actor, $file);
    Notification::assertSentTo($member, ProjectFileUploadedNotification::class);
    Notification::assertNotSentTo($actor, ProjectFileUploadedNotification::class);

    // 4. Meeting scheduled
    app(MeetingService::class)->createMeeting($actor, [
        'project_id' => $project->id,
        'title' => 'Sprint Planning',
        'start_time' => now()->addDay()->toIso8601String(),
        'end_time' => now()->addDay()->addHour()->toIso8601String(),
        'participant_ids' => [$member->id],
    ]);
    Notification::assertSentTo($member, MeetingScheduledNotification::class);
    Notification::assertNotSentTo($actor, MeetingScheduledNotification::class);
});
