<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Http\Requests\Ai\FeedbackRequest;
use App\Http\Requests\Ai\SendAiMessageRequest;
use App\Http\Resources\AiMessageResource;
use App\Jobs\ProcessAiAttachmentJob;
use App\Models\AiMessage;
use App\Services\AiChatService;
use App\Services\GeminiChatService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiChatController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(
        protected AiChatService $aiChatService,
        protected GeminiChatService $geminiChatService,
    ) {}

    public function send(SendAiMessageRequest $request): JsonResponse
    {
        try {
            if ($request->hasFile('attachment')) {
                return $this->handleAttachmentMessage($request);
            }

            return $this->handleTextMessage($request);
        } catch (AuthorizationException) {
            return $this->failureResponse(__('ai.conversation_forbidden'), 'forbidden', [], 403);
        } catch (RuntimeException $e) {
            return $this->failureResponse(__('ai.provider_unavailable'), 'ai_unavailable', [], 503);
        } catch (Throwable $e) {
            Log::error('AiChatController::send unexpected error', [
                'error' => substr($e->getMessage(), 0, 300),
            ]);

            return $this->failureResponse(__('ai.provider_error'), 'server_error', [], 500);
        }
    }

    public function regenerate(Request $request, AiMessage $message): JsonResponse
    {
        // Rate limit: 10 per minute per user
        $key = 'ai-regenerate:'.($request->user()?->id ?? $request->ip());
        $maxAttempts = 10;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->failureResponse(
                'Too many regeneration attempts. Please wait.',
                'rate_limited',
                [],
                429,
            );
        }
        RateLimiter::hit($key, $decaySeconds);

        try {
            if ($message->role !== AiMessageRole::ASSISTANT) {
                return $this->failureResponse(__('ai.message_not_assistant'), 'invalid_request', [], 422);
            }

            $conversation = $message->conversation;
            $this->authorize('view', $conversation);

            // Find the user message this reply was for
            $userMessage = null;
            if ($message->parent_message_id) {
                $userMessage = AiMessage::find($message->parent_message_id);
            }

            if (! $userMessage) {
                // Find the last user message before this assistant message
                $userMessage = AiMessage::query()
                    ->where('ai_conversation_id', $conversation->id)
                    ->where('role', AiMessageRole::USER->value)
                    ->where('created_at', '<', $message->created_at)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            // Build context up to (and including) that user message
            $contents = $this->aiChatService->buildContents($conversation);

            // Call Gemini
            $result = $this->geminiChatService->chat($request->user(), $contents);

            // Create a NEW assistant message (do not overwrite existing)
            $newMessage = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'parent_message_id' => $message->parent_message_id,
                'role' => AiMessageRole::ASSISTANT,
                'status' => AiMessageStatus::COMPLETED,
                'content' => $result['content'],
                'provider' => $result['provider'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $this->successResponse(
                new AiMessageResource($newMessage),
                __('ai.regenerated'),
            );
        } catch (AuthorizationException) {
            return $this->failureResponse(__('ai.conversation_forbidden'), 'forbidden', [], 403);
        } catch (RuntimeException $e) {
            return $this->failureResponse(__('ai.provider_unavailable'), 'ai_unavailable', [], 503);
        } catch (Throwable $e) {
            Log::error('AiChatController::regenerate unexpected error', [
                'error' => substr($e->getMessage(), 0, 300),
            ]);

            return $this->failureResponse(__('ai.provider_error'), 'server_error', [], 500);
        }
    }

    public function feedback(FeedbackRequest $request, AiMessage $message): JsonResponse
    {
        try {
            if ($message->role !== AiMessageRole::ASSISTANT) {
                return $this->failureResponse(__('ai.message_not_assistant'), 'invalid_request', [], 422);
            }

            $conversation = $message->conversation;
            $this->authorize('view', $conversation);

            $feedbackValue = $request->input('feedback');

            $message->update([
                'feedback' => $feedbackValue,
            ]);

            return $this->successResponse(
                new AiMessageResource($message->fresh()),
                __('ai.feedback_updated'),
            );
        } catch (AuthorizationException) {
            return $this->failureResponse(__('ai.conversation_forbidden'), 'forbidden', [], 403);
        } catch (Throwable $e) {
            Log::error('AiChatController::feedback unexpected error', [
                'error' => substr($e->getMessage(), 0, 300),
            ]);

            return $this->failureResponse(__('ai.provider_error'), 'server_error', [], 500);
        }
    }

    private function handleTextMessage(SendAiMessageRequest $request): JsonResponse
    {
        $result = $this->aiChatService->sendMessage(
            user: $request->user(),
            conversationId: $request->integer('conversation_id', 0) ?: null,
            messageText: $request->string('message')->toString(),
        );

        return $this->successResponse([
            'conversation' => $result['conversation'],
            'user_message' => new AiMessageResource($result['user_message']),
            'assistant_message' => new AiMessageResource($result['assistant_message']),
        ], __('ai.message_sent'));
    }

    private function handleAttachmentMessage(SendAiMessageRequest $request): JsonResponse
    {
        $file = $request->file('attachment');

        // Server-side MIME validation via finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        if (! in_array($realMime, $allowedMimes, true)) {
            return $this->failureResponse('Invalid file type detected.', 'invalid_file', [], 422);
        }

        $storagePath = Storage::disk('private')->putFileAs(
            'ai-attachments/'.date('Y/m'),
            $file,
            Str::uuid().'.'.$file->getClientOriginalExtension(),
        );

        $conversationId = $request->integer('conversation_id', 0) ?: null;
        $conversation = $this->aiChatService->getOrCreateConversation($request->user(), $conversationId);

        $messageText = $request->string('message')->toString();

        // Update title if null
        if ($conversation->title === null && $messageText !== '') {
            $conversation->title = mb_substr($messageText, 0, 80);
            $conversation->save();
        } elseif ($conversation->title === null) {
            $conversation->title = mb_substr($file->getClientOriginalName(), 0, 80);
            $conversation->save();
        }

        $userMessage = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::USER,
            'content' => $messageText ?: null,
            'status' => AiMessageStatus::PROCESSING,
            'attachment_path' => $storagePath,
            'attachment_name' => $file->getClientOriginalName(),
            'attachment_mime' => $realMime,
        ]);

        $assistantMessage = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::ASSISTANT,
            'content' => null,
            'status' => AiMessageStatus::PROCESSING,
        ]);

        ProcessAiAttachmentJob::dispatch($userMessage, $assistantMessage);

        return $this->successResponse([
            'conversation' => $conversation,
            'user_message' => new AiMessageResource($userMessage),
        ], __('ai.processing'), [], 202);
    }
}
