<?php

namespace App\Http\Controllers\Api\V1\Chats;

use App\Enums\ChatCallType;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chats\InitiateCallRequest;
use App\Http\Requests\Chats\StoreChatMessageRequest;
use App\Http\Requests\Chats\StoreChatThreadReplyRequest;
use App\Http\Requests\Chats\StoreChatThreadRequest;
use App\Http\Requests\Chats\StoreDirectConversationRequest;
use App\Http\Requests\Chats\StoreProjectChannelRequest;
use App\Http\Requests\Chats\StoreTeamChannelRequest;
use App\Http\Requests\Chats\UpdateChatMessageRequest;
use App\Http\Resources\ChatCallResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatMessageThreadResource;
use App\Http\Resources\ChatThreadReplyResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\ChatCall;
use App\Models\ChatMessage;
use App\Models\ChatMessageThread;
use App\Models\ChatThreadReply;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use App\Services\ChatCallService;
use App\Services\ChatMessageService;
use App\Services\ChatThreadService;
use App\Services\ConversationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ConversationService $conversationService,
        protected ChatMessageService $messageService,
        protected ChatThreadService $threadService,
        protected ChatCallService $callService
    ) {}

    /**
     * List current user's conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->listUserConversations(
            $request->user(),
            $request->only(['search', 'type'])
        );

        return $this->successResponse(ConversationResource::collection($conversations));
    }

    /**
     * Start or retrieve direct conversation.
     */
    public function storeDirect(StoreDirectConversationRequest $request): JsonResponse
    {
        $recipient = User::findOrFail($request->validated('user_id'));
        $conversation = $this->conversationService->getOrCreateDirectConversation($request->user(), $recipient);

        return $this->successResponse(
            new ConversationResource($conversation),
            __('chats.direct_conversation_ready'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Create a public project channel.
     */
    public function storeProjectChannel(StoreProjectChannelRequest $request): JsonResponse
    {
        $project = Project::findOrFail($request->validated('project_id'));

        if (! $request->user()->isAdmin() && ! $request->user()->isProjectManagerOf($project)) {
            return $this->failureResponse(__('chats.unauthorized_channel_creation'), 'forbidden', status: Response::HTTP_FORBIDDEN);
        }

        $channel = $this->conversationService->createProjectChannel(
            $request->user(),
            $project,
            $request->validated('name'),
            $request->validated('description')
        );

        return $this->successResponse(
            new ConversationResource($channel),
            __('chats.project_channel_created'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Create a team channel.
     */
    public function storeTeamChannel(StoreTeamChannelRequest $request): JsonResponse
    {
        $team = ProjectTeam::findOrFail($request->validated('team_id'));

        $isLeader = $team->leader_id === $request->user()->id;
        $isPm = $request->user()->isProjectManagerOf($team->project_id);

        if (! $request->user()->isAdmin() && ! $isLeader && ! $isPm) {
            return $this->failureResponse(__('chats.unauthorized_team_channel_creation'), 'forbidden', status: Response::HTTP_FORBIDDEN);
        }

        $channel = $this->conversationService->createTeamChannel(
            $request->user(),
            $team,
            $request->validated('name'),
            $request->validated('description')
        );

        return $this->successResponse(
            new ConversationResource($channel),
            __('chats.team_channel_created'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * View conversation details.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['participants', 'project', 'team']);
        $conversation->unread_count = $this->conversationService->getUnreadCount($conversation, $request->user());

        return $this->successResponse(new ConversationResource($conversation));
    }

    /**
     * Get conversation participants.
     */
    public function participants(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->successResponse(UserSummaryResource::collection($conversation->participants));
    }

    /**
     * List messages in a conversation.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $perPage = (int) $request->query('per_page', 25);
        $paginator = $this->messageService->listMessages($conversation, $request->only('search'), $perPage);

        return $this->successResponse(
            ChatMessageResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Send message.
     */
    public function sendMessage(StoreChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('participate', $conversation);

        $message = $this->messageService->sendMessage(
            $conversation,
            $request->user(),
            $request->input('body'),
            $request->file('attachments', [])
        );

        return $this->successResponse(
            new ChatMessageResource($message),
            __('chats.message_sent'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Edit message.
     */
    public function updateMessage(UpdateChatMessageRequest $request, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        $this->authorize('update', $message);

        $updated = $this->messageService->updateMessage($message, $request->validated('body'));

        return $this->successResponse(new ChatMessageResource($updated), __('chats.message_updated'));
    }

    /**
     * Delete message.
     */
    public function deleteMessage(Request $request, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $this->messageService->deleteMessage($message);

        return $this->successResponse(null, __('chats.message_deleted'));
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $lastMessageId = $request->input('last_read_message_id');
        $this->messageService->markAsRead($conversation, $request->user(), $lastMessageId ? (int) $lastMessageId : null);

        return $this->successResponse(null, __('chats.messages_marked_read'));
    }

    /**
     * Start or fetch thread for a message.
     */
    public function storeThread(StoreChatThreadRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $parentMessage = ChatMessage::where('conversation_id', $conversation->id)
            ->findOrFail($request->validated('parent_message_id'));

        $thread = $this->threadService->getOrCreateThread(
            $parentMessage,
            $request->user(),
            $request->validated('title')
        );

        return $this->successResponse(
            new ChatMessageThreadResource($thread),
            __('chats.thread_created'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * List threads in a conversation.
     */
    public function listThreads(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $threads = $conversation->threads()
            ->with(['creator:id,name,avatar', 'parentMessage.sender:id,name,avatar'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(ChatMessageThreadResource::collection($threads));
    }

    /**
     * Show thread details.
     */
    public function showThread(Request $request, ChatMessageThread $thread): JsonResponse
    {
        $this->authorize('view', $thread->conversation);

        $thread->load(['creator:id,name,avatar', 'parentMessage.sender:id,name,avatar']);

        return $this->successResponse(new ChatMessageThreadResource($thread));
    }

    /**
     * List thread replies.
     */
    public function threadReplies(Request $request, ChatMessageThread $thread): JsonResponse
    {
        $this->authorize('view', $thread->conversation);

        $perPage = (int) $request->query('per_page', 25);
        $paginator = $this->threadService->listReplies($thread, $request->only('search'), $perPage);

        return $this->successResponse(
            ChatThreadReplyResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Add reply to thread.
     */
    public function addThreadReply(StoreChatThreadReplyRequest $request, ChatMessageThread $thread): JsonResponse
    {
        $this->authorize('view', $thread->conversation);

        $reply = $this->threadService->addReply(
            $thread,
            $request->user(),
            $request->input('body'),
            $request->file('attachments', [])
        );

        return $this->successResponse(
            new ChatThreadReplyResource($reply),
            __('chats.reply_sent'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Edit thread reply.
     */
    public function updateThreadReply(UpdateChatMessageRequest $request, ChatMessageThread $thread, ChatThreadReply $reply): JsonResponse
    {
        $this->authorize('updateReply', $reply);

        $updated = $this->threadService->updateReply($reply, $request->validated('body'));

        return $this->successResponse(new ChatThreadReplyResource($updated), __('chats.reply_updated'));
    }

    /**
     * Delete thread reply.
     */
    public function deleteThreadReply(Request $request, ChatMessageThread $thread, ChatThreadReply $reply): JsonResponse
    {
        $this->authorize('deleteReply', $reply);

        $this->threadService->deleteReply($reply);

        return $this->successResponse(null, __('chats.reply_deleted'));
    }

    /**
     * Broadcast typing indicator.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('participate', $conversation);

        $isTyping = $request->boolean('is_typing', true);

        try {
            broadcast(new UserTyping($conversation->id, $request->user(), $isTyping))->toOthers();
        } catch (\Throwable) {
        }

        return $this->successResponse(null);
    }

    /**
     * Initiate a call inside conversation.
     */
    public function initiateCall(InitiateCallRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('participate', $conversation);

        $type = ChatCallType::tryFrom($request->input('type', 'video')) ?? ChatCallType::VIDEO;
        $result = $this->callService->initiateCall($conversation, $request->user(), $type);

        return $this->successResponse(
            [
                'call' => new ChatCallResource($result['call']),
                'join_url' => $result['join_url'],
            ],
            __('chats.call_initiated'),
            status: Response::HTTP_CREATED
        );
    }

    /**
     * Join active call.
     */
    public function joinCall(Request $request, ChatCall $call): JsonResponse
    {
        $this->authorize('view', $call->conversation);

        $joinUrl = $this->callService->getJoinUrl($call, $request->user());

        return $this->successResponse([
            'call' => new ChatCallResource($call->load('initiator:id,name,avatar')),
            'join_url' => $joinUrl,
        ]);
    }

    /**
     * End call.
     */
    public function endCall(Request $request, ChatCall $call): JsonResponse
    {
        $this->authorize('view', $call->conversation);

        $call = $this->callService->endCall($call);

        return $this->successResponse(new ChatCallResource($call), __('chats.call_ended'));
    }
}
