<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReportType;
use App\Enums\SystemRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserActivityType;
use App\Enums\UserAvailabilityStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTeam;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProjectTeamSeeder extends Seeder
{
    /**
     * Run the database seeds idempotently.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('Password@123');

        // -------------------------------------------------------------
        // 1. Seed Realistic Users
        // -------------------------------------------------------------
        $admin = User::updateOrCreate(
            ['email' => 'admin@collabspace.com'],
            [
                'name' => 'System Admin',
                'password' => $defaultPassword,
                'system_role' => SystemRole::ADMIN,
                'email_verified_at' => now(),
                'suspended_at' => null,
            ]
        );

        $mohamed = User::updateOrCreate(
            ['email' => 'mohamed.salah@collabspace.com'],
            [
                'name' => 'Mohamed Salah',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => null,
                'job_title' => 'UI/UX Designer',
                'years_of_experience' => 6,
                'phone' => '+20 100 123 4567',
                'bio' => 'Senior UI/UX Designer dedicated to building intuitive and clean user interfaces for web and mobile platforms.',
                'hours_worked' => 120,
                'availability_status' => UserAvailabilityStatus::AVAILABLE,
            ]
        );

        $mohanad = User::updateOrCreate(
            ['email' => 'mohanad.wahib@collabspace.com'],
            [
                'name' => 'Mohanad Wahib',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => null,
            ]
        );

        $sara = User::updateOrCreate(
            ['email' => 'sara.hassan@collabspace.com'],
            [
                'name' => 'Sara Hassan',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => null,
            ]
        );

        $ali = User::updateOrCreate(
            ['email' => 'ali.mohamed@collabspace.com'],
            [
                'name' => 'Ali Mohamed',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => null,
            ]
        );

        $khalid = User::updateOrCreate(
            ['email' => 'khalid.mousa@collabspace.com'],
            [
                'name' => 'Khalid Mousa',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => null,
            ]
        );

        $ahmed = User::updateOrCreate(
            ['email' => 'ahmed.hassan@collabspace.com'],
            [
                'name' => 'Ahmed Hassan',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => null,
            ]
        );

        $suspended = User::updateOrCreate(
            ['email' => 'suspended@collabspace.com'],
            [
                'name' => 'Suspended User',
                'password' => $defaultPassword,
                'system_role' => SystemRole::USER,
                'email_verified_at' => now(),
                'suspended_at' => now(),
            ]
        );

        // -------------------------------------------------------------
        // 2. Project 1: Alpha (Matches Figma Overview & Teams screens)
        // -------------------------------------------------------------
        $alpha = Project::updateOrCreate(
            ['name' => 'Alpha'],
            [
                'description' => 'This project focuses on organizing tasks, tracking progress, and coordinating team efforts to ensure timely delivery and clear visibility across all project activities.',
                'project_type' => 'SaaS',
                'start_date' => '2025-10-12',
                'deadline' => '2025-12-25',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'color' => '#3B82F6',
                'icon' => 'briefcase',
                'archived_at' => null,
            ]
        );

        // Attach members to Alpha
        $this->syncProjectMember($alpha->id, $mohamed->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($alpha->id, $sara->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($alpha->id, $mohanad->id, ProjectRole::MEMBER->value);
        $this->syncProjectMember($alpha->id, $ali->id, ProjectRole::MEMBER->value);
        $this->syncProjectMember($alpha->id, $khalid->id, ProjectRole::MEMBER->value);
        $this->syncProjectMember($alpha->id, $ahmed->id, ProjectRole::MEMBER->value);

        // Teams inside Alpha
        $uiUx = ProjectTeam::updateOrCreate(
            ['project_id' => $alpha->id, 'name' => 'UI/UX'],
            ['color' => '#FCE7F3', 'leader_id' => $mohanad->id]
        );
        $this->syncTeamMember($uiUx->id, $mohanad->id, 'Leader');
        $this->syncTeamMember($uiUx->id, $sara->id, 'UI Designer');
        $this->syncTeamMember($uiUx->id, $ali->id, 'UX Researcher');

        $frontend = ProjectTeam::updateOrCreate(
            ['project_id' => $alpha->id, 'name' => 'Frontend'],
            ['color' => '#F3E8FF', 'leader_id' => $mohanad->id]
        );
        $this->syncTeamMember($frontend->id, $mohanad->id, 'Front Leader');
        $this->syncTeamMember($frontend->id, $ali->id, 'React Dev');

        $backend = ProjectTeam::updateOrCreate(
            ['project_id' => $alpha->id, 'name' => 'Backend'],
            ['color' => '#CCFBF1', 'leader_id' => $mohanad->id]
        );
        $this->syncTeamMember($backend->id, $mohanad->id, 'Back Leader');
        $this->syncTeamMember($backend->id, $khalid->id, 'API Engineer');
        $this->syncTeamMember($backend->id, $ahmed->id, 'Database Engineer');

        $marketing = ProjectTeam::updateOrCreate(
            ['project_id' => $alpha->id, 'name' => 'Marketing'],
            ['color' => '#F3F4F6', 'leader_id' => $mohanad->id]
        );
        $this->syncTeamMember($marketing->id, $mohanad->id, 'Marketing Lead');
        $this->syncTeamMember($marketing->id, $sara->id, 'Content Strategist');

        // -------------------------------------------------------------
        // 3. Project 2: SepetGo (Matches Figma All Projects + Sole PM Test Case)
        // -------------------------------------------------------------
        $sepetGo = Project::updateOrCreate(
            ['name' => 'SepetGo'],
            [
                'description' => 'Fast grocery delivery e-commerce application.',
                'project_type' => 'E-Commerce',
                'start_date' => '2025-12-12',
                'deadline' => '2025-12-14',
                'status' => ProjectStatus::DONE,
                'priority' => ProjectPriority::MEDIUM,
                'color' => '#14B8A6',
                'icon' => 'shopping-bag',
                'archived_at' => null,
            ]
        );

        $this->syncProjectMember($sepetGo->id, $khalid->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($sepetGo->id, $ali->id, ProjectRole::MEMBER->value);
        $this->syncProjectMember($sepetGo->id, $ahmed->id, ProjectRole::MEMBER->value);

        // -------------------------------------------------------------
        // 4. Project 3: Legacy Portal (Archived Project Test Case)
        // -------------------------------------------------------------
        $legacyPortal = Project::updateOrCreate(
            ['name' => 'Legacy Portal'],
            [
                'description' => 'Internal employee portal slated for sunset.',
                'project_type' => 'Internal Tool',
                'start_date' => '2025-01-10',
                'deadline' => '2025-06-30',
                'status' => ProjectStatus::ARCHIVED,
                'priority' => ProjectPriority::LOW,
                'color' => '#6B7280',
                'icon' => 'archive',
                'archived_at' => now()->subDays(5),
            ]
        );

        $this->syncProjectMember($legacyPortal->id, $mohamed->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($legacyPortal->id, $ahmed->id, ProjectRole::MEMBER->value);

        // -------------------------------------------------------------
        // 5. Projects 4, 5, 6 (Pagination, Search & Sorting Test Cases)
        // -------------------------------------------------------------
        $mobileBanking = Project::updateOrCreate(
            ['name' => 'Mobile Banking App'],
            [
                'description' => 'Secure next-generation fintech app for retail banking.',
                'project_type' => 'Mobile App',
                'start_date' => '2026-01-01',
                'deadline' => null,
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'color' => '#10B981',
                'icon' => 'credit-card',
                'archived_at' => null,
            ]
        );
        $this->syncProjectMember($mobileBanking->id, $mohamed->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($mobileBanking->id, $ali->id, ProjectRole::MEMBER->value);

        $analytics = Project::updateOrCreate(
            ['name' => 'Analytics Dashboard'],
            [
                'description' => 'Enterprise real-time analytics aggregation dashboard.',
                'project_type' => 'Data Platform',
                'start_date' => '2026-02-15',
                'deadline' => '2027-06-01',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::LOW,
                'color' => '#8B5CF6',
                'icon' => 'bar-chart',
                'archived_at' => null,
            ]
        );
        $this->syncProjectMember($analytics->id, $mohamed->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($analytics->id, $sara->id, ProjectRole::MEMBER->value);

        $cloudMigration = Project::updateOrCreate(
            ['name' => 'Cloud Migration'],
            [
                'description' => 'Infrastructure lift-and-shift to managed Kubernetes cluster.',
                'project_type' => 'DevOps',
                'start_date' => '2025-03-01',
                'deadline' => '2025-08-30',
                'status' => ProjectStatus::DONE,
                'priority' => ProjectPriority::MEDIUM,
                'color' => '#F59E0B',
                'icon' => 'cloud',
                'archived_at' => null,
            ]
        );
        $this->syncProjectMember($cloudMigration->id, $mohamed->id, ProjectRole::PROJECT_MANAGER->value);
        $this->syncProjectMember($cloudMigration->id, $ahmed->id, ProjectRole::MEMBER->value);

        // -------------------------------------------------------------
        // 6. Slice 3 Demo Data: Tasks for Alpha Project
        // -------------------------------------------------------------
        $task1 = Task::updateOrCreate(
            ['project_id' => $alpha->id, 'title' => 'Design System Tokens'],
            [
                'project_team_id' => $uiUx->id,
                'created_by' => $mohamed->id,
                'reviewer_id' => $sara->id,
                'description' => 'Establish unified color palette, typography and component spacing tokens.',
                'status' => TaskStatus::COMPLETED,
                'priority' => TaskPriority::HIGH,
                'progress' => 100,
                'due_date' => '2025-11-01',
                'position' => 0,
            ]
        );
        $task1->assignees()->sync([$mohanad->id, $ali->id]);

        $task2 = Task::updateOrCreate(
            ['project_id' => $alpha->id, 'title' => 'Figma UI Components & Interactive Prototypes'],
            [
                'project_team_id' => $uiUx->id,
                'created_by' => $mohanad->id,
                'reviewer_id' => $mohamed->id,
                'description' => 'Build high-fidelity wireframes and prototype user flows for dashboard screens.',
                'status' => TaskStatus::IN_REVIEW,
                'priority' => TaskPriority::HIGH,
                'progress' => 85,
                'due_date' => '2025-11-15',
                'position' => 0,
            ]
        );
        $task2->assignees()->sync([$sara->id]);

        $task3 = Task::updateOrCreate(
            ['project_id' => $alpha->id, 'title' => 'Implement REST API Auth & Teams Endpoints'],
            [
                'project_team_id' => $backend->id,
                'created_by' => $mohamed->id,
                'reviewer_id' => $mohanad->id,
                'description' => 'Develop Sanctum authentication, Google OAuth, and Project/Team CRUD endpoints.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::MEDIUM,
                'progress' => 60,
                'due_date' => '2025-11-25',
                'position' => 0,
            ]
        );
        $task3->assignees()->sync([$khalid->id, $ahmed->id]);

        $task4 = Task::updateOrCreate(
            ['project_id' => $alpha->id, 'title' => 'Setup React Component Library & Tailwind'],
            [
                'project_team_id' => $frontend->id,
                'created_by' => $mohanad->id,
                'reviewer_id' => null,
                'description' => 'Configure Vite, TailwindCSS, Lucide icons, and base UI primitives.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'progress' => 0,
                'due_date' => '2025-12-05',
                'position' => 0,
            ]
        );
        $task4->assignees()->sync([$ali->id]);

        $task5 = Task::updateOrCreate(
            ['project_id' => $alpha->id, 'title' => 'Market Research and Competitor Analysis'],
            [
                'project_team_id' => $marketing->id,
                'created_by' => $mohamed->id,
                'reviewer_id' => $sara->id,
                'description' => 'Analyze competitive collaboration platforms and draft feature differentiation doc.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'progress' => 0,
                'due_date' => '2025-12-10',
                'position' => 1,
            ]
        );
        $task5->assignees()->sync([$mohanad->id, $sara->id]);

        // -------------------------------------------------------------
        // 7. Slice 3 Demo Data: Files for Alpha Project
        // -------------------------------------------------------------
        $storageDisk = Storage::disk('public');
        $directory = "projects/{$alpha->id}/files";

        $sampleFiles = [
            [
                'name' => 'Project Requirements Specification',
                'original_name' => 'Alpha_Requirements_v1.0.pdf',
                'file_name' => 'alpha_requirements.pdf',
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'content' => "%PDF-1.4\n1 0 obj\n<< /Title (Alpha Requirements) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
                'uploader' => $mohamed,
            ],
            [
                'name' => 'Database Architecture Diagram',
                'original_name' => 'schema_design.png',
                'file_name' => 'schema_design.png',
                'mime_type' => 'image/png',
                'extension' => 'png',
                'content' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
                'uploader' => $khalid,
            ],
            [
                'name' => 'Brand Guidelines and UI Colors',
                'original_name' => 'brand_guidelines.pdf',
                'file_name' => 'brand_guidelines.pdf',
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'content' => "%PDF-1.4\n1 0 obj\n<< /Title (Brand Guidelines) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
                'uploader' => $sara,
            ],
        ];

        foreach ($sampleFiles as $sample) {
            $filePath = "{$directory}/{$sample['file_name']}";
            $storageDisk->put($filePath, $sample['content']);
            $size = strlen($sample['content']);

            ProjectFile::updateOrCreate(
                ['project_id' => $alpha->id, 'name' => $sample['name']],
                [
                    'uploaded_by' => $sample['uploader']->id,
                    'original_name' => $sample['original_name'],
                    'file_path' => $filePath,
                    'mime_type' => $sample['mime_type'],
                    'extension' => $sample['extension'],
                    'size_bytes' => $size,
                ]
            );
        }

        // -------------------------------------------------------------
        // 6. Seed Realistic Activities & Reports for Mohamed Salah
        // -------------------------------------------------------------
        $firstTask = Task::where('project_id', $alpha->id)->first();

        // Activities
        UserActivity::firstOrCreate(
            [
                'user_id' => $mohamed->id,
                'action_type' => UserActivityType::TASK_COMPLETED,
                'description' => 'Completed task: High-Fidelity Figma Wireframes',
            ],
            [
                'subject_type' => $firstTask ? Task::class : null,
                'subject_id' => $firstTask?->id,
                'created_at' => now()->subHours(2),
            ]
        );

        UserActivity::firstOrCreate(
            [
                'user_id' => $mohamed->id,
                'action_type' => UserActivityType::FILE_UPLOADED,
                'description' => 'Uploaded file: Brand Guidelines and UI Colors',
            ],
            [
                'subject_type' => null,
                'subject_id' => null,
                'created_at' => now()->subHours(5),
            ]
        );

        UserActivity::firstOrCreate(
            [
                'user_id' => $mohamed->id,
                'action_type' => UserActivityType::TASK_UPDATED,
                'description' => 'Updated task status: Dashboard Layout Design',
            ],
            [
                'subject_type' => $firstTask ? Task::class : null,
                'subject_id' => $firstTask?->id,
                'created_at' => now()->subDay(),
            ]
        );

        // Reports
        Report::firstOrCreate(
            [
                'user_id' => $mohamed->id,
                'report_type' => ReportType::PROJECT_PROGRESS,
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->toDateString(),
            ],
            [
                'project_id' => $alpha->id,
                'note' => 'Monthly progress evaluation for Project Alpha UI sprint.',
                'summary_snapshot' => [
                    'projects_evaluated' => 1,
                    'projects_completed' => 0,
                    'total_tasks_in_period' => 5,
                    'completed_tasks_in_period' => 3,
                    'completion_percentage' => 60,
                ],
            ]
        );
    }

    /**
     * Idempotently sync a project membership.
     */
    protected function syncProjectMember(int $projectId, int $userId, string $role): void
    {
        DB::table('project_user')->updateOrInsert(
            [
                'project_id' => $projectId,
                'user_id' => $userId,
            ],
            [
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Idempotently sync a team membership.
     */
    protected function syncTeamMember(int $teamId, int $userId, ?string $roleTitle): void
    {
        DB::table('project_team_user')->updateOrInsert(
            [
                'project_team_id' => $teamId,
                'user_id' => $userId,
            ],
            [
                'role_title' => $roleTitle,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
