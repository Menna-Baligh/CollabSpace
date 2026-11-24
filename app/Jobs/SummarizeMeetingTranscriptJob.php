<?php

namespace App\Jobs;

use App\Contracts\AiSummarizerServiceInterface;
use App\Enums\MeetingSummaryStatus;
use App\Models\MeetingSummary;
use App\Models\MeetingTranscript;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SummarizeMeetingTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public MeetingTranscript $transcript) {}

    public function handle(AiSummarizerServiceInterface $summarizerService): void
    {
        $meeting = $this->transcript->meeting;

        $summary = MeetingSummary::firstOrCreate(
            [
                'meeting_id' => $meeting->id,
                'transcript_id' => $this->transcript->id,
            ],
            [
                'provider' => 'gemini',
                'status' => MeetingSummaryStatus::PROCESSING,
            ]
        );
        $summary->update(['status' => MeetingSummaryStatus::PROCESSING]);

        try {
            $result = $summarizerService->summarize($this->transcript);

            $summary->update([
                'status' => MeetingSummaryStatus::COMPLETED,
                'summary' => $result['summary'],
                'key_points' => $result['key_points'],
                'action_items' => $result['action_items'],
            ]);
        } catch (Throwable $e) {
            Log::error('SummarizeMeetingTranscriptJob failed: '.$e->getMessage());
            $summary->update([
                'status' => MeetingSummaryStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
