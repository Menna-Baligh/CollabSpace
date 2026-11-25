<?php

namespace App\Jobs;

use App\Enums\AiMessageStatus;
use App\Models\AiMessage;
use App\Services\AiChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiAttachmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public AiMessage $userMessage,
        public AiMessage $assistantMessage,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60];
    }

    public function handle(AiChatService $service): void
    {
        // Idempotency: skip if already finished
        $this->assistantMessage->refresh();

        if (in_array($this->assistantMessage->status, [
            AiMessageStatus::COMPLETED,
            AiMessageStatus::FAILED,
        ], true)) {
            return;
        }

        $service->processAttachmentMessage($this->userMessage, $this->assistantMessage);

        // Broadcast event if it exists
        $this->broadcastUpdate($this->assistantMessage->fresh());
    }

    public function failed(Throwable $e): void
    {
        try {
            $this->assistantMessage->update([
                'status' => AiMessageStatus::FAILED,
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);

            $this->broadcastUpdate($this->assistantMessage->fresh());
        } catch (Throwable $inner) {
            // Ignore secondary failures
        }

        Log::error('ProcessAiAttachmentJob failed', [
            'assistant_message_id' => $this->assistantMessage->id,
            'error' => substr($e->getMessage(), 0, 300),
        ]);
    }

    private function broadcastUpdate(?AiMessage $message): void
    {
        if ($message === null) {
            return;
        }

        $eventClass = 'App\\Events\\AiMessageUpdated';

        if (class_exists($eventClass)) {
            try {
                event(new $eventClass($message));
            } catch (Throwable) {
                // Broadcast failure must not break the job
            }
        }
    }
}
