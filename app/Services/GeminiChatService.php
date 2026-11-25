<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiChatService
{
    private const MAX_TOOL_CALLS = 3;

    public function __construct(
        protected WorkspaceContextBuilder $contextBuilder
    ) {}

    /**
     * Send a chat conversation to Gemini with optional workspace tool calling and attachment.
     *
     * @param  list<array{role: string, parts: list<array<string, mixed>>}>  $contents
     * @return array{content: string, provider: string, model: string, input_tokens: ?int, output_tokens: ?int}
     */
    public function chat(User $user, array $contents): array
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            throw new RuntimeException(__('ai.provider_unavailable'));
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemInstruction = [
            'parts' => [
                [
                    'text' => 'You are CollabSpace AI Assistant. You help users with both general knowledge questions and questions about their CollabSpace workspace (projects, tasks, teams, files, meetings). '
                        .'When a user asks about their workspace data, use the provided workspace tools to retrieve accurate, authorized information. '
                        .'Never invent workspace data, task statuses, or meeting details. If data is unavailable, state that clearly.',
                ],
            ],
        ];

        $tools = [
            [
                'functionDeclarations' => $this->contextBuilder->getToolDeclarations(),
            ],
        ];

        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $hasTokenData = false;

        for ($step = 0; $step <= self::MAX_TOOL_CALLS; $step++) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
                ->timeout(60)
                ->post($endpoint, [
                    'systemInstruction' => $systemInstruction,
                    'contents' => $contents,
                    'tools' => $tools,
                ]);

            if ($response->failed()) {
                throw new RuntimeException(__('ai.provider_error'));
            }

            $json = $response->json();

            if (isset($json['usageMetadata'])) {
                $hasTokenData = true;
                $totalInputTokens += (int) ($json['usageMetadata']['promptTokenCount'] ?? 0);
                $totalOutputTokens += (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0);
            }

            $candidate = $json['candidates'][0]['content'] ?? null;
            $parts = $candidate['parts'] ?? [];

            $functionCall = null;
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCall = $part['functionCall'];
                    break;
                }
            }

            if ($functionCall === null) {
                $textParts = [];
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        $textParts[] = $part['text'];
                    }
                }

                $reply = trim(implode("\n", $textParts));
                if ($reply === '') {
                    throw new RuntimeException(__('ai.invalid_response'));
                }

                return [
                    'content' => $reply,
                    'provider' => 'gemini',
                    'model' => $model,
                    'input_tokens' => $hasTokenData ? $totalInputTokens : null,
                    'output_tokens' => $hasTokenData ? $totalOutputTokens : null,
                ];
            }

            if ($step === self::MAX_TOOL_CALLS) {
                throw new RuntimeException(__('ai.provider_error'));
            }

            $toolName = (string) ($functionCall['name'] ?? '');
            $toolArgs = is_array($functionCall['args'] ?? null) ? $functionCall['args'] : [];
            $toolResult = $this->contextBuilder->executeOperation($user, $toolName, $toolArgs);

            $contents[] = [
                'role' => 'model',
                'parts' => $parts,
            ];
            $contents[] = [
                'role' => 'user',
                'parts' => [
                    [
                        'functionResponse' => [
                            'name' => $toolName,
                            'response' => $toolResult,
                        ],
                    ],
                ],
            ];
        }

        throw new RuntimeException(__('ai.provider_error'));
    }
}
