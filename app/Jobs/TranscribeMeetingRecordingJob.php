<?php

namespace App\Jobs;

use App\Contracts\TranscriptionServiceInterface;
use App\Enums\MeetingRecordingStatus;
use App\Enums\MeetingTranscriptStatus;
use App\Models\MeetingRecording;
use App\Models\MeetingTranscript;
use App\Models\MeetingTranscriptSegment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranscribeMeetingRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public MeetingRecording $recording) {}

    public function handle(TranscriptionServiceInterface $transcriptionService): void
    {
        $meeting = $this->recording->meeting;
        $meeting->update(['recording_status' => MeetingRecordingStatus::PROCESSING]);

        $transcript = MeetingTranscript::firstOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'status' => MeetingTranscriptStatus::PROCESSING,
                'language' => 'en',
            ]
        );
        $transcript->update(['status' => MeetingTranscriptStatus::PROCESSING]);

        try {
            $result = $transcriptionService->transcribe($this->recording);

            DB::transaction(function () use ($transcript, $meeting, $result) {
                $transcript->update([
                    'status' => MeetingTranscriptStatus::COMPLETED,
                    'language' => $result['language'] ?? 'en',
                    'full_text' => $result['full_text'],
                ]);

                // Clear any previous segments if retried
                $transcript->segments()->delete();

                foreach ($result['segments'] as $seg) {
                    MeetingTranscriptSegment::create([
                        'transcript_id' => $transcript->id,
                        'speaker_label' => $seg['speaker_label'] ?? null,
                        'start_time' => $seg['start_time'],
                        'end_time' => $seg['end_time'],
                        'text' => $seg['text'],
                    ]);
                }

                $meeting->update(['recording_status' => MeetingRecordingStatus::COMPLETED]);
            });

            // Dispatch automatic summarization job
            SummarizeMeetingTranscriptJob::dispatch($transcript);
        } catch (Throwable $e) {
            Log::error('TranscribeMeetingRecordingJob failed: '.$e->getMessage());
            $transcript->update([
                'status' => MeetingTranscriptStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $meeting->update(['recording_status' => MeetingRecordingStatus::FAILED]);
            throw $e;
        }
    }
}
