<?php

namespace App\Services;

use App\Enums\ChatCallStatus;
use App\Enums\ChatCallType;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Models\ChatCall;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatCallService
{
    public function __construct(protected JitsiService $jitsiService) {}

    /**
     * Initiate a call inside a conversation.
     */
    public function initiateCall(Conversation $conversation, User $initiator, ChatCallType $type): array
    {
        $roomName = $this->jitsiService->generateRoomName('call_'.$conversation->id);

        $call = DB::transaction(function () use ($conversation, $initiator, $type, $roomName) {
            return ChatCall::create([
                'conversation_id' => $conversation->id,
                'initiator_id' => $initiator->id,
                'room_name' => $roomName,
                'type' => $type,
                'status' => ChatCallStatus::ACTIVE,
                'started_at' => now(),
            ]);
        });

        $call->load('initiator:id,name,avatar');
        $joinUrl = $this->jitsiService->buildJoinUrl($roomName, $initiator, $type === ChatCallType::VIDEO);

        try {
            broadcast(new CallInitiated($call, $joinUrl))->toOthers();
        } catch (Throwable) {
        }

        return [
            'call' => $call,
            'join_url' => $joinUrl,
        ];
    }

    /**
     * Join an active call.
     */
    public function getJoinUrl(ChatCall $call, User $user): string
    {
        return $this->jitsiService->buildJoinUrl($call->room_name, $user, $call->type === ChatCallType::VIDEO);
    }

    /**
     * End a call.
     */
    public function endCall(ChatCall $call): ChatCall
    {
        $call->update([
            'status' => ChatCallStatus::ENDED,
            'ended_at' => now(),
        ]);

        try {
            broadcast(new CallEnded($call))->toOthers();
        } catch (Throwable) {
        }

        return $call;
    }
}
