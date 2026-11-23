<?php

use App\Enums\UserAvailabilityStatus;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('email');
            $table->string('phone', 30)->nullable()->after('avatar');
            $table->string('availability_status', 30)->default(UserAvailabilityStatus::AVAILABLE->value)->after('phone');
            $table->string('job_title', 150)->nullable()->after('availability_status');
            $table->unsignedTinyInteger('years_of_experience')->nullable()->after('job_title');
            $table->text('bio')->nullable()->after('years_of_experience');
            $table->unsignedInteger('hours_worked')->default(0)->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar',
                'phone',
                'availability_status',
                'job_title',
                'years_of_experience',
                'bio',
                'hours_worked',
            ]);
        });
    }
};
