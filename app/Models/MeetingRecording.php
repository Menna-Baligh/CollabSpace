<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'storage_path',
        'disk',
        'duration_seconds',
        'file_size',
        'mime_type',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
