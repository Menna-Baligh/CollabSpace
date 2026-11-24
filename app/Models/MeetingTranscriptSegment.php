<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingTranscriptSegment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'transcript_id',
        'speaker_label',
        'speaker_user_id',
        'start_time',
        'end_time',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'float',
            'end_time' => 'float',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(MeetingTranscript::class, 'transcript_id');
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'speaker_user_id');
    }
}
