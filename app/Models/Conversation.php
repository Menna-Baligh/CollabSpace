<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'project_id',
        'team_id',
        'name',
        'description',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ProjectTeam::class, 'team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_message_id', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ChatMessageThread::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(ChatCall::class);
    }

    public function isDirect(): bool
    {
        return $this->type === ConversationType::DIRECT;
    }

    public function isProjectChannel(): bool
    {
        return $this->type === ConversationType::PROJECT_CHANNEL;
    }

    public function isTeamChannel(): bool
    {
        return $this->type === ConversationType::TEAM_CHANNEL;
    }
}
