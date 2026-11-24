<?php

namespace App\Services;

use App\Events\ThreadReplySent;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatMessageThread;
use App\Models\ChatThreadReply;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class ChatThreadService
{
    /**
     * Create or retrieve a thread for a message.
     */
    public function getOrCreateThread(ChatMessage $message, User $creator, string $title): ChatMessageThread
    {
        if ($message->thread) {
            return $message->thread->load(['creator:id,name,avatar', 'parentMessage']);
        }

        return DB::transaction(function () use ($message, $creator, $title) {
            return ChatMessageThread::create([
                'conversation_id' => $message->conversation_id,
                'parent_message_id' => $message->id,
                'title' => $title,
                'created_by' => $creator->id,
                'replies_count' => 0,
            ])->load(['creator:id,name,avatar', 'parentMessage']);
        });
    }

    /**
     * List replies in a thread.
     */
    public function listReplies(ChatMessageThread $thread, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $thread->replies()
            ->with(['sender:id,name,avatar', 'attachments'])
            ->orderBy('id', 'asc');

        if (! empty($filters['search'])) {
            $query->where('body', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Add reply to thread.
     *
     * @param  array<UploadedFile>  $files
     */
    public function addReply(ChatMessageThread $thread, User $sender, ?string $body, array $files = []): ChatThreadReply
    {
        if (empty($body) && empty($files)) {
            throw new InvalidArgumentException('Reply cannot be empty without attachments.');
        }

        $disk = config('filesystems.default', 'local');
        $storedPaths = [];

        try {
            $reply = DB::transaction(function () use ($thread, $sender, $body, $files, $disk, &$storedPaths) {
                $rep = ChatThreadReply::create([
                    'thread_id' => $thread->id,
                    'sender_id' => $sender->id,
                    'body' => $body,
                ]);

                foreach ($files as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('chat_attachments', $disk);
                        $storedPaths[] = $path;
                        ChatAttachment::create([
                            'thread_reply_id' => $rep->id,
                            'uploader_id' => $sender->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                            'disk' => $disk,
                        ]);
                    }
                }

                $thread->increment('replies_count');
                $thread->update(['last_reply_at' => now()]);

                return $rep;
            });
        } catch (Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }

        $reply->load(['sender:id,name,avatar', 'attachments']);

        try {
            broadcast(new ThreadReplySent($reply))->toOthers();
        } catch (Throwable) {
        }

        return $reply;
    }

    /**
     * Update a reply.
     */
    public function updateReply(ChatThreadReply $reply, string $body): ChatThreadReply
    {
        $reply->update([
            'body' => $body,
            'edited_at' => now(),
        ]);

        return $reply->load(['sender:id,name,avatar', 'attachments']);
    }

    /**
     * Delete a reply.
     */
    public function deleteReply(ChatThreadReply $reply): bool
    {
        return DB::transaction(function () use ($reply) {
            $thread = $reply->thread;
            $deleted = $reply->delete();
            if ($deleted && $thread->replies_count > 0) {
                $thread->decrement('replies_count');
            }

            return (bool) $deleted;
        });
    }
}
