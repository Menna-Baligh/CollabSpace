<?php

namespace App\Models;

use App\Enums\AiFeedbackType;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_messages';

    protected $fillable = [
        'ai_conversation_id',
        'parent_message_id',
        'role',
        'content',
        'status',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'link_preview',
        'feedback',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'status' => AiMessageStatus::class,
            'feedback' => AiFeedbackType::class,
            'link_preview' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /**
     * @return BelongsTo<AiMessage, $this>
     */
    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'parent_message_id');
    }
}
