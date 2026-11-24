<?php

namespace App\Http\Controllers\Api\V1\Chats;

use App\Http\Controllers\Controller;
use App\Models\ChatAttachment;
use App\Services\ConversationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ChatAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(protected ConversationService $conversationService) {}

    /**
     * Download attachment file securely.
     */
    public function download(Request $request, ChatAttachment $attachment)
    {
        $conversation = $attachment->message?->conversation ?? $attachment->threadReply?->thread?->conversation;

        if (! $conversation || ! $this->conversationService->canAccess($request->user(), $conversation)) {
            return $this->failureResponse(__('chats.unauthorized_attachment_access'), 'forbidden', status: Response::HTTP_FORBIDDEN);
        }

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->file_path)) {
            return $this->failureResponse('Attachment file not found.', 'not_found', status: Response::HTTP_NOT_FOUND);
        }

        return $disk->download($attachment->file_path, $attachment->file_name);
    }
}
