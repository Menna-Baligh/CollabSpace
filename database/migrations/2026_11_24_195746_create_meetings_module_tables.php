<?php

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingRecordingStatus;
use App\Enums\MeetingStatus;
use App\Enums\MeetingSummaryStatus;
use App\Enums\MeetingTranscriptStatus;
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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('timezone')->default('UTC');
            $table->string('status')->default(MeetingStatus::SCHEDULED->value);
            $table->string('provider')->default('jitsi');
            $table->string('room_name')->unique();
            $table->string('join_url')->nullable();
            $table->string('recording_status')->default(MeetingRecordingStatus::NONE->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status'], 'idx_meetings_project_status');
            $table->index('start_time', 'idx_meetings_start_time');
            $table->index('room_name', 'idx_meetings_room');
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default(MeetingParticipantStatus::INVITED->value);
            $table->boolean('has_attended')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id'], 'uq_meeting_user');
            $table->index(['user_id', 'status'], 'idx_meeting_participants_user');
        });

        Schema::create('meeting_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('disk')->default('local');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->default('video/mp4');
            $table->timestamps();
        });

        Schema::create('meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('meeting_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meeting_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('language')->default('en');
            $table->string('status')->default(MeetingTranscriptStatus::PENDING->value);
            $table->string('provider')->default('whisper');
            $table->longText('full_text')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained('meeting_transcripts')->cascadeOnDelete();
            $table->string('speaker_label')->nullable();
            $table->foreignId('speaker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('start_time', 8, 2);
            $table->decimal('end_time', 8, 2);
            $table->text('text');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('meeting_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained('meeting_transcripts')->cascadeOnDelete();
            $table->string('provider')->default('gemini');
            $table->text('summary')->nullable();
            $table->json('key_points')->nullable();
            $table->json('action_items')->nullable();
            $table->string('status')->default(MeetingSummaryStatus::PENDING->value);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_summaries');
        Schema::dropIfExists('meeting_transcript_segments');
        Schema::dropIfExists('meeting_transcripts');
        Schema::dropIfExists('meeting_comments');
        Schema::dropIfExists('meeting_notes');
        Schema::dropIfExists('meeting_recordings');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
