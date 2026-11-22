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
        Schema::create('project_team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_team_id')->constrained('project_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_title')->nullable();
            $table->timestamps();

            $table->unique(['project_team_id', 'user_id'], 'idx_project_team_user_team_user_unique');
            $table->index(['project_team_id'], 'idx_project_team_user_team_id');
            $table->index(['user_id'], 'idx_project_team_user_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_team_user');
    }
};
