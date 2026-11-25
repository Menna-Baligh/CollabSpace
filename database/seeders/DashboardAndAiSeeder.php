<?php

namespace Database\Seeders;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Enums\MeetingStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DashboardAndAiSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'm.salah@collabspace.test'],
            [
                'name' => 'Mohamed Salah',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $teammate = User::firstOrCreate(
            ['email' => 'sara.ahmed@collabspace.test'],
            [
                'name' => 'Sara Ahmed',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $project = Project::firstOrCreate(
            ['name' => 'Alpha Platform Launch'],
            [
                'description' => 'Core platform launch and API integration.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => now()->subDays(15)->toDateString(),
                'deadline' => now()->addDays(30)->toDateString(),
            ]
        );

        if (! $project->members()->where('users.id', $user->id)->exists()) {
            $project->members()->attach($user->id, ['role' => ProjectRole::PROJECT_MANAGER->value]);
        }
        if (! $project->members()->where('users.id', $teammate->id)->exists()) {
            $project->members()->attach($teammate->id, ['role' => ProjectRole::MEMBER->value]);
        }

        $team = ProjectTeam::firstOrCreate(
            ['project_id' => $project->id, 'name' => 'Alpha Core Team'],
            ['leader_id' => $user->id, 'color' => '#3B82F6']
        );

        if (! $team->members()->where('users.id', $user->id)->exists()) {
            $team->members()->attach($user->id, ['role_title' => 'Tech Lead']);
        }
        if (! $team->members()->where('users.id', $teammate->id)->exists()) {
            $team->members()->attach($teammate->id, ['role_title' => 'Backend Engineer']);
        }

        $tasksData = [
            ['title' => 'Design Dashboard API', 'status' => TaskStatus::COMPLETED, 'priority' => TaskPriority::HIGH, 'progress' => 100],
            ['title' => 'Implement Global Search', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'progress' => 60],
            ['title' => 'Review AI Assistant Endpoints', 'status' => TaskStatus::IN_REVIEW, 'priority' => TaskPriority::MEDIUM, 'progress' => 85],
            ['title' => 'Write API Documentation', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::LOW, 'progress' => 0],
        ];

        foreach ($tasksData as $tData) {
            $task = Task::firstOrCreate(
                ['project_id' => $project->id, 'title' => $tData['title']],
                [
                    'project_team_id' => $team->id,
                    'created_by' => $user->id,
                    'status' => $tData['status'],
                    'priority' => $tData['priority'],
                    'progress' => $tData['progress'],
                    'due_date' => now()->addDays(5)->toDateString(),
                ]
            );

            if (! $task->assignees()->where('users.id', $user->id)->exists()) {
                $task->assignees()->attach($user->id);
            }

            if (! TaskStatusHistory::where('task_id', $task->id)->exists()) {
                TaskStatusHistory::create([
                    'task_id' => $task->id,
                    'from_status' => null,
                    'to_status' => $tData['status']->value,
                    'changed_by' => $user->id,
                    'is_baseline' => true,
                    'changed_at' => $task->created_at ?? now(),
                ]);
            }
        }

        ProjectFile::firstOrCreate(
            ['project_id' => $project->id, 'name' => 'Alpha_Architecture_Spec.pdf'],
            [
                'uploaded_by' => $user->id,
                'original_name' => 'Alpha_Architecture_Spec.pdf',
                'file_path' => 'demo/placeholder.pdf', // Demo record
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => 245760,
            ]
        );

        $meeting = Meeting::firstOrCreate(
            ['creator_id' => $user->id, 'title' => 'Alpha Weekly Sync'],
            [
                'project_id' => $project->id,
                'description' => 'Weekly progress and blocker review.',
                'start_time' => now()->addDay()->setTime(10, 0),
                'end_time' => now()->addDay()->setTime(11, 0),
                'timezone' => 'UTC',
                'status' => MeetingStatus::SCHEDULED,
                'provider' => 'jitsi',
                'room_name' => 'alpha-weekly-sync',
                'join_url' => 'https://meet.jit.si/alpha-weekly-sync',
            ]
        );

        if (! $meeting->participants()->where('users.id', $user->id)->exists()) {
            $meeting->participants()->attach($user->id);
        }

        // Demo AI Conversation (demo data, not generated by live Gemini API)
        $conversation = AiConversation::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Project Status Check'],
            ['last_message_at' => now()]
        );

        if ($conversation->messages()->count() === 0) {
            $u1 = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => AiMessageRole::USER,
                'content' => 'What is the status of Alpha Platform Launch? https://collabspace.test/projects/1',
                'status' => AiMessageStatus::COMPLETED,
                'link_preview' => ['url' => 'https://collabspace.test/projects/1', 'domain' => 'collabspace.test'],
            ]);

            AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'parent_message_id' => $u1->id,
                'role' => AiMessageRole::ASSISTANT,
                'content' => 'Alpha Platform Launch currently has 4 tasks: 1 completed, 1 in progress, 1 in review, and 1 pending.',
                'status' => AiMessageStatus::COMPLETED,
                'provider' => 'demo',
                'model' => 'demo-seeder',
            ]);
        }
    }
}
