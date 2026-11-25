<?php

namespace App\Services;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AiChatService
{
    public function __construct(
        protected GeminiChatService $gemini,
        protected LinkPreviewService $linkPreview,
    ) {}

    /**
     * Get an existing conversation or create a new one.
     *
     * @throws AuthorizationException
     */
    public function getOrCreateConversation(User $user, ?int $conversationId): AiConversation
    {
        if ($conversationId === null) {
            return AiConversation::create([
                'user_id' => $user->id,
            ]);
        }

        $conversation = AiConversation::findOrFail($conversationId);

        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('ai.conversation_forbidden'));
        }

        return $conversation;
    }

    /**
     * Send a user message and return the AI response synchronously.
     *
     * @param  array{path?: string, name?: string, mime?: string}  $attachment
     * @return array{conversation: AiConversation, user_message: AiMessage, assistant_message: AiMessage}
     *
     * @throws AuthorizationException
     */
    public function sendMessage(
        User $user,
        ?int $conversationId,
        string $messageText,
        array $attachment = [],
    ): array {
        $conversation = $this->getOrCreateConversation($user, $conversationId);

        // Update title if null
        if ($conversation->title === null) {
            $conversation->title = mb_substr($messageText, 0, 80);
            $conversation->save();
        }

        // Extract link preview
        $linkPreview = $this->linkPreview->extract($messageText);

        // Save user message
        $userMessage = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::USER,
            'content' => $messageText,
            'status' => AiMessageStatus::COMPLETED,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_name' => $attachment['name'] ?? null,
            'attachment_mime' => $attachment['mime'] ?? null,
            'link_preview' => $linkPreview,
        ]);

        // Build Gemini contents
        $contents = $this->buildContents($conversation, $attachment);

        // Placeholder for assistant message
        $assistantMessage = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::ASSISTANT,
            'content' => null,
            'status' => AiMessageStatus::PROCESSING,
        ]);

        try {
            $result = $this->gemini->chat($user, $contents);

            $assistantMessage->update([
                'content' => $result['content'],
                'status' => AiMessageStatus::COMPLETED,
                'provider' => $result['provider'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);
        } catch (Throwable $e) {
            $assistantMessage->update([
                'status' => AiMessageStatus::FAILED,
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            Log::warning('AiChatService: Gemini chat failed', [
                'conversation_id' => $conversation->id,
                'error' => substr($e->getMessage(), 0, 200),
            ]);
        }

        $conversation->update(['last_message_at' => now()]);

        return [
            'conversation' => $conversation->fresh(),
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
        ];
    }

    /**
     * Process an attachment message asynchronously (called from job).
     */
    public function processAttachmentMessage(AiMessage $userMsg, AiMessage $assistantMsg): void
    {
        $conversation = $userMsg->conversation()->with('user')->firstOrFail();
        $user = $conversation->user;

        $attachment = [];
        if ($userMsg->attachment_path) {
            $attachment = [
                'path' => $userMsg->attachment_path,
                'name' => $userMsg->attachment_name,
                'mime' => $userMsg->attachment_mime,
            ];
        }

        $contents = $this->buildContents($conversation, $attachment);

        try {
            $result = $this->gemini->chat($user, $contents);

            $assistantMsg->update([
                'content' => $result['content'],
                'status' => AiMessageStatus::COMPLETED,
                'provider' => $result['provider'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);
        } catch (Throwable $e) {
            $assistantMsg->update([
                'status' => AiMessageStatus::FAILED,
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            Log::warning('AiChatService: processAttachmentMessage failed', [
                'conversation_id' => $conversation->id,
                'error' => substr($e->getMessage(), 0, 200),
            ]);
        }

        $conversation->update(['last_message_at' => now()]);
    }

    /**
     * Build the Gemini contents array from the conversation history.
     *
     * @param  array{path?: string, name?: string, mime?: string}  $pendingAttachment
     * @return list<array{role: string, parts: list<array<string, mixed>>}>
     */
    public function buildContents(AiConversation $conversation, array $pendingAttachment = []): array
    {
        $limit = (int) config('services.gemini.context_messages', 20);

        $messages = $conversation->messages()
            ->whereIn('status', [AiMessageStatus::COMPLETED->value, AiMessageStatus::PROCESSING->value])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $contents = [];

        foreach ($messages as $message) {
            $role = $message->role === AiMessageRole::ASSISTANT ? 'model' : 'user';
            $parts = [];

            if ($message->content) {
                $parts[] = ['text' => $message->content];
            }

            // Include attachment for user messages
            if ($message->role === AiMessageRole::USER && $message->attachment_path) {
                $attachmentParts = $this->buildAttachmentParts(
                    $message->attachment_path,
                    $message->attachment_mime ?? '',
                );
                $parts = array_merge($parts, $attachmentParts);
            }

            if (empty($parts)) {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => $parts,
            ];
        }

        // If a pending attachment was supplied separately (async flow), add it at the end
        if (! empty($pendingAttachment['path'])) {
            $attachmentParts = $this->buildAttachmentParts(
                $pendingAttachment['path'],
                $pendingAttachment['mime'] ?? '',
            );

            if (! empty($attachmentParts)) {
                // Merge into last user turn or create new one
                $lastIndex = count($contents) - 1;
                if ($lastIndex >= 0 && $contents[$lastIndex]['role'] === 'user') {
                    $contents[$lastIndex]['parts'] = array_merge(
                        $contents[$lastIndex]['parts'],
                        $attachmentParts,
                    );
                } else {
                    $contents[] = [
                        'role' => 'user',
                        'parts' => $attachmentParts,
                    ];
                }
            }
        }

        return $contents;
    }

    /**
     * Build the attachment parts for Gemini contents.
     *
     * @return list<array<string, mixed>>
     */
    private function buildAttachmentParts(string $path, string $mime): array
    {
        $parts = [];

        if (! Storage::disk('private')->exists($path)) {
            return $parts;
        }

        $isPdf = str_contains(strtolower($mime), 'pdf');
        $isImage = str_starts_with(strtolower($mime), 'image/');

        if ($isImage) {
            try {
                $raw = Storage::disk('private')->get($path);
                $b64 = base64_encode($raw);
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $mime,
                        'data' => $b64,
                    ],
                ];
            } catch (Throwable $e) {
                Log::warning('AiChatService: failed to encode image attachment', [
                    'error' => substr($e->getMessage(), 0, 200),
                ]);
            }
        } elseif ($isPdf) {
            $text = $this->extractPdfText($path);
            if ($text !== '') {
                $parts[] = ['text' => "[Attached PDF content]\n".$text];
            }
        }

        return $parts;
    }

    /**
     * Extract readable text from a PDF using simple regex/ASCII stripping.
     */
    private function extractPdfText(string $storagePath): string
    {
        try {
            $fullPath = Storage::disk('private')->path($storagePath);
            $raw = file_get_contents($fullPath);

            if ($raw === false) {
                return '';
            }

            // Extract text between BT/ET markers (PDF text objects)
            $text = '';
            if (preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $matches)) {
                foreach ($matches[1] as $block) {
                    // Extract string literals from parentheses
                    if (preg_match_all('/\(([^)\\\\]|\\\\.)*\)/s', $block, $strMatches)) {
                        foreach ($strMatches[0] as $str) {
                            $str = substr($str, 1, -1); // strip parens
                            $str = stripslashes($str);
                            $text .= $str.' ';
                        }
                    }
                }
            }

            // Fallback: strip binary and keep readable ASCII lines
            if (strlen(trim($text)) < 50) {
                $stripped = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $raw);
                $lines = explode("\n", $stripped ?? '');
                $kept = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strlen($line) > 4 && preg_match('/[a-zA-Z]{2,}/', $line)) {
                        $kept[] = $line;
                    }
                }
                $text = implode("\n", $kept);
            }

            $text = trim((string) preg_replace('/\s{3,}/', ' ', $text));

            if (strlen($text) < 50) {
                return '[PDF content could not be extracted. Please describe its contents in your message.]';
            }

            return mb_substr($text, 0, 8000);
        } catch (Throwable $e) {
            Log::warning('AiChatService: PDF text extraction failed', [
                'error' => substr($e->getMessage(), 0, 200),
            ]);

            return '';
        }
    }
}
