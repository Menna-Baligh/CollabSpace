<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\TaskDueSoonNotification;
use App\Notifications\Tasks\TaskOverdueNotification;
use Illuminate\Support\Facades\Notification;

it('sends due soon and overdue task reminders without duplicates and skips completed or archived tasks', function () {
    Notification::fake();

    $user = User::factory()->create();
    $activeProject = Project::factory()->create(['status' => ProjectStatus::ACTIVE]);
    $archivedProject = Project::factory()->create(['status' => ProjectStatus::ARCHIVED, 'archived_at' => now()]);

    $dueSoonTask = Task::create([
        'project_id' => $activeProject->id,
        'created_by' => $user->id,
        'title' => 'Due Soon Task',
        'status' => TaskStatus::TODO,
        'due_date' => now()->addHours(12)->toDateString(),
    ]);
    $dueSoonTask->assignees()->attach($user->id);

    $overdueTask = Task::create([
        'project_id' => $activeProject->id,
        'created_by' => $user->id,
        'title' => 'Overdue Task',
        'status' => TaskStatus::IN_PROGRESS,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);
    $overdueTask->assignees()->attach($user->id);

    $completedTask = Task::create([
        'project_id' => $activeProject->id,
        'created_by' => $user->id,
        'title' => 'Completed Task',
        'status' => TaskStatus::COMPLETED,
        'due_date' => now()->subDay()->toDateString(),
    ]);
    $completedTask->assignees()->attach($user->id);

    $archivedTask = Task::create([
        'project_id' => $archivedProject->id,
        'created_by' => $user->id,
        'title' => 'Archived Task',
        'status' => TaskStatus::TODO,
        'due_date' => now()->subDay()->toDateString(),
    ]);
    $archivedTask->assignees()->attach($user->id);

    $this->artisan('notifications:send-task-reminders')->assertSuccessful();

    Notification::assertSentTo($user, TaskDueSoonNotification::class, 1);
    Notification::assertSentTo($user, TaskOverdueNotification::class, 1);

    // Running again should not send duplicates
    $this->artisan('notifications:send-task-reminders')->assertSuccessful();

    Notification::assertSentTo($user, TaskDueSoonNotification::class, 1);
    Notification::assertSentTo($user, TaskOverdueNotification::class, 1);
});
