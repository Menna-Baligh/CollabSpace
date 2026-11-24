<?php

namespace App\Services;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConversationService
{
    /**
     * List user conversations with unread message counts, latest message, and eager-loaded relations.
     */
    public function listUserConversations(User $user, array $filters = []): Collection
    {
        $query = Conversation::query()
            ->where(function (Builder $q) use ($user) {
                // Either user is direct participant
                $q->whereHas('participants', function (Builder $pq) use ($user) {
                    $pq->where('user_id', $user->id);
                })
                // Or user is a member of the project for a project channel
                    ->orWhere(function (Builder $cq) use ($user) {
                        $cq->where('type', ConversationType::PROJECT_CHANNEL->value)
                            ->whereHas('project.members', function (Builder $mq) use ($user) {
                                $mq->where('user_id', $user->id);
                            });
                    })
                // Or user is a member of the team for a team channel
                    ->orWhere(function (Builder $tq) use ($user) {
                        $tq->where('type', ConversationType::TEAM_CHANNEL->value)
                            ->whereHas('team.members', function (Builder $tmq) use ($user) {
                                $tmq->where('user_id', $user->id);
                            });
                    });
            })
            ->with([
                'participants',
                'project:id,name',
                'team:id,name,project_id',
                'messages' => function ($mq) {
                    $mq->latest()->limit(1)->with('sender:id,name,avatar');
                },
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $sq) use ($search) {
                $sq->where('name', 'like', $search)
                    ->orWhereHas('project', fn ($pq) => $pq->where('name', 'like', $search))
                    ->orWhereHas('participants', fn ($uq) => $uq->where('name', 'like', $search));
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $conversations = $query->get();

        // Calculate unread count for each conversation
        foreach ($conversations as $conversation) {
            $conversation->unread_count = $this->getUnreadCount($conversation, $user);
        }

        return $conversations;
    }

    /**
     * Get unread message count for a user in a conversation.
     */
    public function getUnreadCount(Conversation $conversation, User $user): int
    {
        $participant = $conversation->participants->firstWhere('id', $user->id);
        $lastReadId = $participant?->pivot?->last_read_message_id ?? 0;

        return $conversation->messages()
            ->where('id', '>', $lastReadId)
            ->where('sender_id', '!=', $user->id)
            ->count();
    }

    /**
     * Find or create a direct conversation between two users.
     */
    public function getOrCreateDirectConversation(User $user, User $recipient): Conversation
    {
        if ($user->id === $recipient->id) {
            throw new InvalidArgumentException('Cannot start direct conversation with yourself.');
        }

        // Check if existing direct conversation exists
        $existing = Conversation::query()
            ->where('type', ConversationType::DIRECT->value)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $recipient->id))
            ->first();

        if ($existing) {
            return $existing->load(['participants', 'project', 'team']);
        }

        return DB::transaction(function () use ($user, $recipient) {
            $conversation = Conversation::create([
                'type' => ConversationType::DIRECT,
                'created_by' => $user->id,
            ]);

            $conversation->participants()->attach([$user->id, $recipient->id]);

            return $conversation->load(['participants']);
        });
    }

    /**
     * Create a public project channel.
     */
    public function createProjectChannel(User $user, Project $project, string $name, ?string $description = null): Conversation
    {
        return DB::transaction(function () use ($user, $project, $name, $description) {
            $conversation = Conversation::create([
                'type' => ConversationType::PROJECT_CHANNEL,
                'project_id' => $project->id,
                'name' => $name,
                'description' => $description,
                'created_by' => $user->id,
            ]);

            // Add all project members as participants
            $memberIds = $project->members()->pluck('users.id')->all();
            if (! empty($memberIds)) {
                $conversation->participants()->sync($memberIds);
            }

            return $conversation->load(['participants', 'project']);
        });
    }

    /**
     * Create a team channel.
     */
    public function createTeamChannel(User $user, ProjectTeam $team, string $name, ?string $description = null): Conversation
    {
        return DB::transaction(function () use ($user, $team, $name, $description) {
            $conversation = Conversation::create([
                'type' => ConversationType::TEAM_CHANNEL,
                'project_id' => $team->project_id,
                'team_id' => $team->id,
                'name' => $name,
                'description' => $description,
                'created_by' => $user->id,
            ]);

            // Add team members as participants
            $memberIds = $team->members()->pluck('users.id')->all();
            if (! empty($memberIds)) {
                $conversation->participants()->sync($memberIds);
            }

            return $conversation->load(['participants', 'team']);
        });
    }

    /**
     * Check if user is authorized to access a conversation.
     */
    public function canAccess(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($conversation->isDirect()) {
            return $conversation->participants()->where('user_id', $user->id)->exists();
        }

        if ($conversation->isProjectChannel()) {
            return $conversation->project && $user->isMemberOfProject($conversation->project_id);
        }

        if ($conversation->isTeamChannel()) {
            return $conversation->team && $conversation->team->members()->where('users.id', $user->id)->exists();
        }

        return false;
    }
}
