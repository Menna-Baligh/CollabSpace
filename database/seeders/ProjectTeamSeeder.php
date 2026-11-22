<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
