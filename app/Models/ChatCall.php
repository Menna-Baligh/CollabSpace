<?php

namespace App\Models;

use App\Enums\ChatCallStatus;
use App\Enums\ChatCallType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'initiator_id',
        'room_name',
        'type',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChatCallType::class,
            'status' => ChatCallStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }
}
