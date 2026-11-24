<?php

namespace App\Services;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\MessageUpdated;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ChatMessageService
{
    /**
     * List paginated messages for a conversation.
     */
    public function listMessages(Conversation $conversation, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $conversation->messages()
            ->with(['sender:id,name,avatar', 'attachments', 'thread'])
            ->latest('id');

        if (! empty($filters['search'])) {
            $query->where('body', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Send a new message, optionally with file attachments, and broadcast.
     *
     * @param  array<UploadedFile>  $files
     */
    public function sendMessage(Conversation $conversation, User $sender, ?string $body, array $files = []): ChatMessage
    {
        if (empty($body) && empty($files)) {
            throw new InvalidArgumentException('Message cannot be empty without attachments.');
        }

        $disk = config('filesystems.default', 'local');
        $storedPaths = [];

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $body, $files, $disk, &$storedPaths) {
                $msg = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'body' => $body,
                ]);

                // Save attachments if any
                foreach ($files as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('chat_attachments', $disk);
                        $storedPaths[] = $path;
                        ChatAttachment::create([
                            'message_id' => $msg->id,
                            'uploader_id' => $sender->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                            'disk' => $disk,
                        ]);
                    }
                }

                // Update conversation last_message_at
                $conversation->update(['last_message_at' => now()]);

                // Ensure sender is participant
                $conversation->participants()->syncWithoutDetaching([
                    $sender->id => [
                        'last_read_message_id' => $msg->id,
                        'last_read_at' => now(),
                    ],
                ]);

                return $msg;
            });
        } catch (Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }

        $message->load(['sender:id,name,avatar', 'attachments', 'thread']);

        // Broadcast event safely outside the database transaction
        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (Throwable) {
            // Log/ignore failed broadcasting so persisted message is not lost
        }

        return $message;
    }

    /**
     * Edit message body.
     */
    public function updateMessage(ChatMessage $message, string $newBody): ChatMessage
    {
        $message->update([
            'body' => $newBody,
            'edited_at' => now(),
        ]);

        $message->load(['sender:id,name,avatar', 'attachments', 'thread']);

        try {
            broadcast(new MessageUpdated($message))->toOthers();
        } catch (Throwable) {
        }

        return $message;
    }

    /**
     * Delete message (soft delete) and broadcast.
     */
    public function deleteMessage(ChatMessage $message): bool
    {
        $deleted = $message->delete();

        if ($deleted) {
            try {
                broadcast(new MessageDeleted($message->id, $message->conversation_id))->toOthers();
            } catch (Throwable) {
            }
        }

        return (bool) $deleted;
    }

    /**
     * Mark messages in conversation as read up to a message ID.
     */
    public function markAsRead(Conversation $conversation, User $user, ?int $lastReadMessageId = null): void
    {
        if (! $lastReadMessageId) {
            $lastReadMessageId = $conversation->messages()->latest('id')->value('id') ?? 0;
        }

        $conversation->participants()->syncWithoutDetaching([
            $user->id => [
                'last_read_message_id' => $lastReadMessageId,
                'last_read_at' => now(),
            ],
        ]);

        try {
            broadcast(new MessagesRead($conversation->id, $user->id, $lastReadMessageId))->toOthers();
        } catch (Throwable) {
        }
    }
}
