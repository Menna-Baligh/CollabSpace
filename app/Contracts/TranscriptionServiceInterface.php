<?php

namespace App\Contracts;

use App\Models\MeetingRecording;

interface TranscriptionServiceInterface
{
    /**
     * Transcribe recording and return segments and full text.
     *
     * @return array{language: string, full_text: string, segments: array<array{speaker_label: ?string, start_time: float, end_time: float, text: string}>}
     */
    public function transcribe(MeetingRecording $recording): array;
}
