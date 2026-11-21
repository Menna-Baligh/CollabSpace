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
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('system_role')->default('user')->after('password');
            $table->timestamp('suspended_at')->nullable()->index()->after('system_role');
        });

        Schema::create('oauth_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_id');
            $table->timestamps();

            $table->unique(['provider', 'provider_id'], 'idx_oauth_identities_provider_provider_id');
            $table->unique(['user_id', 'provider'], 'idx_oauth_identities_user_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_identities');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['suspended_at']);
            $table->dropColumn(['suspended_at', 'system_role']);
            $table->string('password')->nullable(false)->change();
        });
    }
};

