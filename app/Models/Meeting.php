<?php

namespace App\Models;

use App\Enums\MeetingRecordingStatus;
use App\Enums\MeetingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'creator_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'timezone',
        'status',
        'provider',
        'room_name',
        'join_url',
        'recording_status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'status' => MeetingStatus::class,
            'recording_status' => MeetingRecordingStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['status', 'has_attended', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MeetingNote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MeetingComment::class);
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(MeetingTranscript::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(MeetingSummary::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('creator_id', $user->id)
                ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $user->id))
                ->orWhereHas('project.members', fn ($mq) => $mq->where('user_id', $user->id));
        });
    }

    public function scopeInWeek(Builder $query, Carbon|string $date): Builder
    {
        $d = Carbon::parse($date);

        return $query->whereBetween('start_time', [$d->copy()->startOfWeek(), $d->copy()->endOfWeek()]);
    }

    public function scopeInMonth(Builder $query, Carbon|string $date): Builder
    {
        $d = Carbon::parse($date);

        return $query->whereBetween('start_time', [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()]);
    }
}
