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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('report_type', 50);
            $table->text('note')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('summary_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'idx_reports_user_created');
            $table->index(['user_id', 'report_type'], 'idx_reports_user_type');
            $table->index('project_id', 'idx_reports_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
