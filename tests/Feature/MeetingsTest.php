<?php

use App\Contracts\AiSummarizerServiceInterface;
use App\Contracts\TranscriptionServiceInterface;
use App\Events\MeetingCommentCreated;
use App\Jobs\SummarizeMeetingTranscriptJob;
use App\Jobs\TranscribeMeetingRecordingJob;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('user can list meetings with calendar filters', function () {
    $meeting = Meeting::create([
        'creator_id' => $this->user->id,
        'title' => 'Weekly Sync',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
        'room_name' => 'meet_test_1',
    ]);

    $response = $this->getJson(route('api.v1.meetings.index', ['view' => 'week']));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'start_time', 'end_time', 'room_name', 'status'],
            ],
            'meta',
        ]);
});

test('user can schedule a meeting and invite participants', function () {
    $response = $this->postJson(route('api.v1.meetings.store'), [
        'title' => 'Product Planning',
        'description' => 'Q4 roadmap discussion',
        'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'end_time' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
        'participant_ids' => [$this->otherUser->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Product Planning');

    $this->assertDatabaseHas('meetings', [
        'title' => 'Product Planning',
        'creator_id' => $this->user->id,
    ]);

    $this->assertDatabaseHas('meeting_participants', [
        'user_id' => $this->otherUser->id,
        'status' => 'invited',
    ]);
});

test('validation prevents invalid meeting schedule with end time before start time', function () {
    $response = $this->postJson(route('api.v1.meetings.store'), [
        'title' => 'Invalid Meeting',
        'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'end_time' => now()->addDay()->format('Y-m-d H:i:s'),
    ]);

    $response->assertStatus(422);
});

test('participant can rsvp to a meeting', function () {
    $meeting = Meeting::create([
        'creator_id' => $this->otherUser->id,
        'title' => 'Design Critique',
        'start_time' => now()->addDays(3),
        'end_time' => now()->addDays(3)->addHour(),
        'room_name' => 'meet_test_2',
    ]);

    $meeting->participants()->attach($this->user->id, ['status' => 'invited']);

    $response = $this->patchJson(route('api.v1.meetings.rsvp', $meeting), [
        'status' => 'accepted',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('meeting_participants', [
        'meeting_id' => $meeting->id,
        'user_id' => $this->user->id,
        'status' => 'accepted',
    ]);
});

test('participant can record attendance and retrieve join url', function () {
    $meeting = Meeting::create([
        'creator_id' => $this->otherUser->id,
        'title' => 'Live Demo',
        'start_time' => now(),
        'end_time' => now()->addHour(),
        'room_name' => 'meet_test_3',
    ]);

    $meeting->participants()->attach($this->user->id, ['status' => 'accepted']);

    $joinRes = $this->getJson(route('api.v1.meetings.join', $meeting));
    $joinRes->assertOk()->assertJsonStructure(['data' => ['join_url']]);

    $attRes = $this->postJson(route('api.v1.meetings.attendance', $meeting), [
        'action' => 'join',
    ]);

    $attRes->assertOk();

    $this->assertDatabaseHas('meeting_participants', [
        'meeting_id' => $meeting->id,
        'user_id' => $this->user->id,
        'has_attended' => true,
    ]);
});

test('user can upload recording and background transcription job is queued', function () {
    Storage::fake('local');
    Queue::fake([TranscribeMeetingRecordingJob::class]);

    $meeting = Meeting::create([
        'creator_id' => $this->user->id,
        'title' => 'Completed Session',
        'start_time' => now()->subHours(2),
        'end_time' => now()->subHour(),
        'room_name' => 'meet_test_4',
    ]);

    $file = UploadedFile::fake()->create('recording.mp4', 2000, 'video/mp4');

    $response = $this->postJson(route('api.v1.meetings.recordings.store', $meeting), [
        'recording' => $file,
        'duration_seconds' => 3600,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('meeting_recordings', [
        'meeting_id' => $meeting->id,
        'duration_seconds' => 3600,
    ]);

    Queue::assertPushed(TranscribeMeetingRecordingJob::class);
});

test('meeting notes and comments work with real-time events', function () {
    Event::fake([MeetingCommentCreated::class]);

    $meeting = Meeting::create([
        'creator_id' => $this->user->id,
        'title' => 'Brainstorming',
        'start_time' => now(),
        'end_time' => now()->addHour(),
        'room_name' => 'meet_test_5',
    ]);

    // Note
    $noteRes = $this->postJson(route('api.v1.meetings.notes.store', $meeting), [
        'content' => 'Takeaways: Deliver feature on Friday.',
    ]);
    $noteRes->assertCreated();
    $this->assertDatabaseHas('meeting_notes', [
        'meeting_id' => $meeting->id,
        'content' => 'Takeaways: Deliver feature on Friday.',
    ]);

    // Comment
    $commentRes = $this->postJson(route('api.v1.meetings.comments.store', $meeting), [
        'comment' => 'Great points mentioned.',
    ]);
    $commentRes->assertCreated();
    Event::assertDispatched(MeetingCommentCreated::class);
});

test('transcription and summarization pipeline runs successfully with configured credentials', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fake_recording.mp4', 'fake-audio-content');

    config(['services.openai.key' => 'test-openai-key']);
    config(['services.gemini.api_key' => 'test-gemini-key']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'language' => 'en',
            'text' => 'Welcome everyone to the meeting.',
            'segments' => [
                ['start' => 0.0, 'end' => 5.0, 'text' => 'Welcome everyone to the meeting.'],
            ],
        ], 200),
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'summary' => 'Team welcomed everyone.',
                                    'key_points' => ['Welcomed team'],
                                    'action_items' => [],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $meeting = Meeting::create([
        'creator_id' => $this->user->id,
        'title' => 'AI Test Meeting',
        'start_time' => now()->subHour(),
        'end_time' => now(),
        'room_name' => 'meet_test_ai',
    ]);

    $recording = MeetingRecording::create([
        'meeting_id' => $meeting->id,
        'storage_path' => 'fake_recording.mp4',
        'disk' => 'local',
        'duration_seconds' => 120,
    ]);

    // Test Transcribe Job
    $transcribeJob = new TranscribeMeetingRecordingJob($recording);
    $mockTranscriptionService = app(TranscriptionServiceInterface::class);
    $transcribeJob->handle($mockTranscriptionService);

    $this->assertDatabaseHas('meeting_transcripts', [
        'meeting_id' => $meeting->id,
        'status' => 'completed',
    ]);

    $transcript = MeetingTranscript::where('meeting_id', $meeting->id)->first();
    expect($transcript)->not->toBeNull();

    // Test Summary Job
    $summarizeJob = new SummarizeMeetingTranscriptJob($transcript);
    $mockSummarizerService = app(AiSummarizerServiceInterface::class);
    $summarizeJob->handle($mockSummarizerService);

    $this->assertDatabaseHas('meeting_summaries', [
        'meeting_id' => $meeting->id,
        'status' => 'completed',
    ]);

    // Test API fetching
    $transcriptRes = $this->getJson(route('api.v1.meetings.transcript', $meeting));
    $transcriptRes->assertOk()->assertJsonStructure(['data' => ['full_text', 'segments']]);

    $summaryRes = $this->getJson(route('api.v1.meetings.summary', $meeting));
    $summaryRes->assertOk()->assertJsonStructure(['data' => ['summary', 'key_points', 'action_items']]);
});

test('missing ai credentials fail transcription and summarization jobs cleanly', function () {
    Storage::fake('local');
    config(['services.openai.key' => null]);
    config(['services.gemini.api_key' => null]);

    $meeting = Meeting::create([
        'creator_id' => $this->user->id,
        'title' => 'No Key Meeting',
        'start_time' => now()->subHour(),
        'end_time' => now(),
        'room_name' => 'meet_test_nokey',
    ]);

    $recording = MeetingRecording::create([
        'meeting_id' => $meeting->id,
        'storage_path' => 'fake.mp4',
        'disk' => 'local',
    ]);

    $transcribeJob = new TranscribeMeetingRecordingJob($recording);
    expect(fn () => $transcribeJob->handle(app(TranscriptionServiceInterface::class)))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseHas('meeting_transcripts', [
        'meeting_id' => $meeting->id,
        'status' => 'failed',
    ]);
});

test('user can edit and delete their own meeting comment, but not others', function () {
    $meeting = Meeting::create([
        'creator_id' => $this->user->id,
        'title' => 'Comment Test',
        'start_time' => now(),
        'end_time' => now()->addHour(),
        'room_name' => 'meet_test_comment',
    ]);

    $myComment = $meeting->comments()->create([
        'user_id' => $this->user->id,
        'comment' => 'Original comment',
    ]);

    $otherComment = $meeting->comments()->create([
        'user_id' => $this->otherUser->id,
        'comment' => 'Other comment',
    ]);

    $this->patchJson(route('api.v1.meetings.comments.update', [$meeting, $myComment]), [
        'comment' => 'Updated comment',
    ])->assertOk()->assertJsonPath('data.comment', 'Updated comment');

    $this->patchJson(route('api.v1.meetings.comments.update', [$meeting, $otherComment]), [
        'comment' => 'Hacked',
    ])->assertStatus(403);

    $this->deleteJson(route('api.v1.meetings.comments.destroy', [$meeting, $myComment]))
        ->assertOk();

    $this->assertSoftDeleted('meeting_comments', ['id' => $myComment->id]);
});
