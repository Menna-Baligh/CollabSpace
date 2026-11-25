<?php

namespace Database\Seeders;

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AiDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Main demo user
        $demoUser = User::firstOrCreate(
            ['email' => 'ai.demo@collabspace.test'],
            [
                'name' => 'Sara Ahmed',
                'password' => Hash::make('Password123!'),
                'system_role' => SystemRole::USER,
                'job_title' => 'Senior Product Engineer',
                'email_verified_at' => now(),
            ]
        );

        $teammate = User::firstOrCreate(
            ['email' => 'omar.demo@collabspace.test'],
            [
                'name' => 'Omar Hassan',
                'password' => Hash::make('Password123!'),
                'system_role' => SystemRole::USER,
                'job_title' => 'UI/UX Designer',
                'email_verified_at' => now(),
            ]
        );

        // 2. Projects
        $project1 = Project::firstOrCreate(
            ['name' => 'CollabSpace Mobile App'],
            [
                'description' => 'Cross-platform mobile application for team collaboration.',
                'project_type' => 'Mobile',
                'start_date' => now()->subDays(30)->toDateString(),
                'deadline' => now()->addDays(45)->toDateString(),
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'color' => '#3B82F6',
            ]
        );

        $project2 = Project::firstOrCreate(
            ['name' => 'AI Analytics Dashboard'],
            [
                'description' => 'Real-time analytics and AI insights dashboard.',
                'project_type' => 'Web',
                'start_date' => now()->subDays(15)->toDateString(),
                'deadline' => now()->addDays(60)->toDateString(),
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'color' => '#10B981',
            ]
        );

        $project1->members()->syncWithoutDetaching([
            $demoUser->id => ['role' => ProjectRole::PROJECT_MANAGER->value],
            $teammate->id => ['role' => ProjectRole::MEMBER->value],
        ]);

        $project2->members()->syncWithoutDetaching([
            $demoUser->id => ['role' => ProjectRole::MEMBER->value],
            $teammate->id => ['role' => ProjectRole::PROJECT_MANAGER->value],
        ]);

        // 3. Teams
        $backendTeam = ProjectTeam::firstOrCreate(
            ['project_id' => $project1->id, 'name' => 'Backend & API Team'],
            ['color' => '#6366F1', 'leader_id' => $demoUser->id]
        );
        $backendTeam->members()->syncWithoutDetaching([
            $demoUser->id => ['role_title' => 'Tech Lead'],
            $teammate->id => ['role_title' => 'API Developer'],
        ]);

        $designTeam = ProjectTeam::firstOrCreate(
            ['project_id' => $project2->id, 'name' => 'Design & Frontend Team'],
            ['color' => '#EC4899', 'leader_id' => $teammate->id]
        );
        $designTeam->members()->syncWithoutDetaching([
            $teammate->id => ['role_title' => 'Design Lead'],
            $demoUser->id => ['role_title' => 'Frontend Engineer'],
        ]);

        // 4. Tasks (overdue, in_progress, todo, completed)
        $tasksData = [
            [
                'project_id' => $project1->id,
                'project_team_id' => $backendTeam->id,
                'title' => 'Fix OAuth Token Refresh Bug',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'progress' => 60,
                'due_date' => now()->subDays(3)->toDateString(), // overdue
            ],
            [
                'project_id' => $project1->id,
                'project_team_id' => $backendTeam->id,
                'title' => 'Implement Push Notifications API',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'progress' => 0,
                'due_date' => now()->addDays(2)->toDateString(),
            ],
            [
                'project_id' => $project1->id,
                'project_team_id' => $backendTeam->id,
                'title' => 'Database Schema Migration',
                'status' => TaskStatus::COMPLETED,
                'priority' => TaskPriority::MEDIUM,
                'progress' => 100,
                'due_date' => now()->subDays(10)->toDateString(),
            ],
            [
                'project_id' => $project2->id,
                'project_team_id' => $designTeam->id,
                'title' => 'Build KPI Charts Widget',
                'status' => TaskStatus::IN_REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'progress' => 90,
                'due_date' => now()->addDays(5)->toDateString(),
            ],
            [
                'project_id' => $project2->id,
                'project_team_id' => $designTeam->id,
                'title' => 'Export Analytics Report to PDF',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'progress' => 0,
                'due_date' => now()->subDay()->toDateString(), // overdue
            ],
        ];

        foreach ($tasksData as $idx => $tData) {
            $task = Task::firstOrCreate(
                ['project_id' => $tData['project_id'], 'title' => $tData['title']],
                array_merge($tData, [
                    'created_by' => $demoUser->id,
                    'reviewer_id' => $teammate->id,
                    'position' => $idx,
                ])
            );
            $task->assignees()->syncWithoutDetaching([$demoUser->id]);
        }

        // 5. Upcoming Meetings
        $meeting1 = Meeting::firstOrCreate(
            ['project_id' => $project1->id, 'title' => 'Mobile App Sprint Planning'],
            [
                'creator_id' => $demoUser->id,
                'description' => 'Plan sprint 4 deliverables and assign tasks.',
                'start_time' => now()->addDay()->setTime(10, 0),
                'end_time' => now()->addDay()->setTime(11, 0),
                'timezone' => 'UTC',
                'status' => MeetingStatus::SCHEDULED,
                'provider' => 'jitsi',
                'room_name' => 'meet_mobile_sprint_demo',
            ]
        );
        $meeting1->participants()->syncWithoutDetaching([
            $demoUser->id => ['status' => MeetingParticipantStatus::ACCEPTED->value],
            $teammate->id => ['status' => MeetingParticipantStatus::INVITED->value],
        ]);

        $meeting2 = Meeting::firstOrCreate(
            ['project_id' => $project2->id, 'title' => 'AI Dashboard Design Review'],
            [
                'creator_id' => $teammate->id,
                'description' => 'Review dashboard wireframes and KPIs.',
                'start_time' => now()->addDays(3)->setTime(14, 0),
                'end_time' => now()->addDays(3)->setTime(15, 0),
                'timezone' => 'UTC',
                'status' => MeetingStatus::SCHEDULED,
                'provider' => 'jitsi',
                'room_name' => 'meet_ai_design_demo',
            ]
        );
        $meeting2->participants()->syncWithoutDetaching([
            $demoUser->id => ['status' => MeetingParticipantStatus::ACCEPTED->value],
            $teammate->id => ['status' => MeetingParticipantStatus::ACCEPTED->value],
        ]);

        // 6. Project Files
        ProjectFile::firstOrCreate(
            ['project_id' => $project1->id, 'name' => 'Mobile Architecture Spec'],
            [
                'uploaded_by' => $demoUser->id,
                'original_name' => 'mobile-architecture-spec.pdf',
                'file_path' => 'projects/'.$project1->id.'/files/demo-spec.pdf',
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => 245760,
            ]
        );
    }
}
