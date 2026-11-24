<?php

namespace App\Services;

use App\Contracts\TranscriptionServiceInterface;
use App\Models\MeetingRecording;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WhisperTranscriptionService implements TranscriptionServiceInterface
{
    public function transcribe(MeetingRecording $recording): array
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new RuntimeException('OpenAI API key is not configured. Transcription service is unavailable.');
        }

        $disk = Storage::disk($recording->disk);
        if (! $disk->exists($recording->storage_path)) {
            throw new RuntimeException("Recording file not found on disk: {$recording->storage_path}");
        }

        $fileStream = $disk->readStream($recording->storage_path);

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->attach('file', $fileStream, basename($recording->storage_path))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
                'timestamp_granularities[]' => 'segment',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Whisper transcription failed: '.$response->body());
        }

        $data = $response->json();
        $segments = [];

        foreach ($data['segments'] ?? [] as $seg) {
            $segments[] = [
                'speaker_label' => null,
                'start_time' => (float) ($seg['start'] ?? 0),
                'end_time' => (float) ($seg['end'] ?? 0),
                'text' => trim($seg['text'] ?? ''),
            ];
        }

        return [
            'language' => $data['language'] ?? 'en',
            'full_text' => trim($data['text'] ?? ''),
            'segments' => $segments,
        ];
    }
}
