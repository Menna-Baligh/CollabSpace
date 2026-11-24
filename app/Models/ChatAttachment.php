<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'thread_reply_id',
        'uploader_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'disk',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function threadReply(): BelongsTo
    {
        return $this->belongsTo(ChatThreadReply::class, 'thread_reply_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getUrlAttribute(): string
    {
        return route('api.v1.chat-attachments.download', ['attachment' => $this->id]);
    }
}
