<?php

namespace App\Models;

use App\Enums\MeetingTranscriptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingTranscript extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'language',
        'status',
        'provider',
        'full_text',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeetingTranscriptStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(MeetingTranscriptSegment::class, 'transcript_id');
    }

    public function summary(): HasOne
    {
        return $this->hasOne(MeetingSummary::class, 'transcript_id');
    }
}
