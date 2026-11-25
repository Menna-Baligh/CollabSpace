<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->enum('reminder_type', ['due_soon', 'overdue']);
            $table->date('due_date');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            // Prevent duplicate reminders of the same type for the same task on the same due date
            $table->unique(['task_id', 'reminder_type', 'due_date'], 'unique_task_reminder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_reminder_logs');
    }
};
