<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Resources\AiConversationResource;
use App\Models\AiConversation;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiConversationController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15, ['id', 'title', 'last_message_at', 'created_at']);

        return $this->successResponse(
            AiConversationResource::collection($conversations)->response()->getData(true),
        );
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        $conversation->setRelation('messages', $messages);

        return $this->successResponse(
            (new AiConversationResource($conversation))
                ->additional([
                    'messages_pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                    ],
                ])
                ->toArray($request),
        );
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return $this->successResponse(null, __('ai.deleted'));
    }
}
