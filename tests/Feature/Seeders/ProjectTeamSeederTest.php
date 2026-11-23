<?php

use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ProjectTeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('project team seeder executes idempotently without duplicate errors', function () {
    $seeder = new ProjectTeamSeeder;

    // First run
    $seeder->run();

    $this->assertDatabaseHas('users', ['email' => 'admin@collabspace.com']);
    $this->assertDatabaseHas('projects', ['name' => 'Alpha']);
    $this->assertDatabaseHas('tasks', ['title' => 'Design System Tokens']);
    $this->assertDatabaseHas('project_files', ['name' => 'Project Requirements Specification']);

    $taskCount = Task::count();
    $fileCount = ProjectFile::count();
    $userCount = User::count();

    // Second run to verify idempotency
    $seeder->run();

    expect(Task::count())->toBe($taskCount)
        ->and(ProjectFile::count())->toBe($fileCount)
        ->and(User::count())->toBe($userCount);
});
