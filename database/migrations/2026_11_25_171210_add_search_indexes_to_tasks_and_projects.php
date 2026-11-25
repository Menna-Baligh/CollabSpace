<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('tasks', 'idx_tasks_project_status')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['project_id', 'status'], 'idx_tasks_project_status');
            });
        }

        if (! Schema::hasIndex('tasks', 'idx_tasks_due_date')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index('due_date', 'idx_tasks_due_date');
            });
        }

        if (! Schema::hasIndex('project_files', 'idx_project_files_created_at')) {
            Schema::table('project_files', function (Blueprint $table) {
                $table->index('created_at', 'idx_project_files_created_at');
            });
        }

        if (! Schema::hasIndex('meetings', 'idx_meetings_status_end_time')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->index(['status', 'end_time'], 'idx_meetings_status_end_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('tasks', 'idx_tasks_project_status')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex('idx_tasks_project_status');
            });
        }

        if (Schema::hasIndex('tasks', 'idx_tasks_due_date')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex('idx_tasks_due_date');
            });
        }

        if (Schema::hasIndex('project_files', 'idx_project_files_created_at')) {
            Schema::table('project_files', function (Blueprint $table) {
                $table->dropIndex('idx_project_files_created_at');
            });
        }

        if (Schema::hasIndex('meetings', 'idx_meetings_status_end_time')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropIndex('idx_meetings_status_end_time');
            });
        }
    }
};
