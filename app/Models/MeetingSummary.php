<?php

namespace App\Models;

use App\Enums\MeetingSummaryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'transcript_id',
        'provider',
        'summary',
        'key_points',
        'action_items',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'action_items' => 'array',
            'status' => MeetingSummaryStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(MeetingTranscript::class);
    }
}
