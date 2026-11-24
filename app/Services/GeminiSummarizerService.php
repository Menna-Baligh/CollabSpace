<?php

namespace App\Services;

use App\Contracts\AiSummarizerServiceInterface;
use App\Models\MeetingTranscript;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiSummarizerService implements AiSummarizerServiceInterface
{
    public function summarize(MeetingTranscript $transcript): array
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Gemini API key is not configured. AI summarization service is unavailable.');
        }

        $prompt = "You are an AI assistant summarizing a business meeting transcript.\n"
            ."Provide a JSON object response with exact structure:\n"
            ."{\n"
            ."  \"summary\": \"Executive summary string\",\n"
            ."  \"key_points\": [\"point 1\", \"point 2\"],\n"
            ."  \"action_items\": [\n"
            ."    {\"task\": \"task description\", \"assignee\": \"person or role or null\", \"due_date\": \"YYYY-MM-DD or null\"}\n"
            ."  ]\n"
            ."}\n\n"
            ."Transcript:\n".$transcript->full_text;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API call failed: '.$response->body());
        }

        $resJson = $response->json();
        $text = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $parsed = json_decode($text, true) ?: [];

        return [
            'summary' => $parsed['summary'] ?? 'Summary not available.',
            'key_points' => $parsed['key_points'] ?? [],
            'action_items' => $parsed['action_items'] ?? [],
        ];
    }
}
