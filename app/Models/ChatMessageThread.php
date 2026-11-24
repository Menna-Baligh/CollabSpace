<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessageThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'parent_message_id',
        'title',
        'created_by',
        'replies_count',
        'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'replies_count' => 'integer',
            'last_reply_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'parent_message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ChatThreadReply::class, 'thread_id');
    }
}
