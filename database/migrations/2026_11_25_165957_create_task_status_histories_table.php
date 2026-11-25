<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_baseline')->default(false);
            $table->timestamp('changed_at');

            $table->index(['task_id', 'changed_at'], 'idx_tsh_task_changed_at');
            $table->index('changed_at', 'idx_tsh_changed_at');
            $table->index(['is_baseline', 'changed_at'], 'idx_tsh_baseline_changed_at');
        });

        // Backfill current_baseline for any existing tasks in chunks to avoid locking large tables
        $now = now();
        DB::table('tasks')
            ->select(['id', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use ($now) {
                $records = [];
                foreach ($tasks as $task) {
                    $records[] = [
                        'task_id' => $task->id,
                        'from_status' => null,
                        'to_status' => $task->status,
                        'changed_by' => null,
                        'is_baseline' => true,
                        'changed_at' => $now,
                    ];
                }
                if (! empty($records)) {
                    DB::table('task_status_histories')->insert($records);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_status_histories');
    }
};
