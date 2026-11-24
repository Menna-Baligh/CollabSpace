<?php

namespace App\Contracts;

use App\Models\MeetingTranscript;

interface AiSummarizerServiceInterface
{
    /**
     * Generate summary, key points, and action items from a transcript.
     *
     * @return array{summary: string, key_points: array<string>, action_items: array<array{task: string, assignee: ?string, due_date: ?string}>}
     */
    public function summarize(MeetingTranscript $transcript): array;
}
