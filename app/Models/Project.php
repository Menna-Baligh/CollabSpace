<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'project_type',
        'start_date',
        'deadline',
        'status',
        'priority',
        'color',
        'icon',
        'archived_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'deadline' => 'date:Y-m-d',
            'status' => ProjectStatus::class,
            'priority' => ProjectPriority::class,
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Check if project is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === ProjectStatus::ARCHIVED || ! is_null($this->archived_at);
    }

    /**
     * Project members relationship.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Project managers relationship.
     *
     * @return BelongsToMany<User, $this>
     */
    public function projectManagers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->wherePivot('role', ProjectRole::PROJECT_MANAGER->value)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Project teams relationship.
     *
     * @return HasMany<ProjectTeam, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(ProjectTeam::class);
    }

    /**
     * Determine if a given user is a member of this project.
     */
    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->members()
            ->where('users.id', $userId)
            ->exists();
    }

    /**
     * Alias for hasMember.
     */
    public function isMemberOfProject(User|int $user): bool
    {
        return $this->hasMember($user);
    }

    /**
     * Determine if a given user is a project manager in this project.
     */
    public function hasProjectManager(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->members()
            ->where('users.id', $userId)
            ->wherePivot('role', ProjectRole::PROJECT_MANAGER->value)
            ->exists();
    }

    /**
     * Count active (non-suspended) project managers.
     */
    public function activeProjectManagersCount(): int
    {
        return $this->members()
            ->wherePivot('role', ProjectRole::PROJECT_MANAGER->value)
            ->whereNull('users.suspended_at')
            ->count();
    }

    /**
     * Safely calculate the timeline progress percentage (0-100).
     */
    public function timelineProgressPercentage(): int
    {
        if (! $this->start_date || ! $this->deadline) {
            return 0;
        }

        $start = $this->start_date instanceof CarbonInterface
            ? $this->start_date
            : now()->parse($this->start_date);

        $end = $this->deadline instanceof CarbonInterface
            ? $this->deadline
            : now()->parse($this->deadline);

        if ($end->lessThan($start)) {
            return 0;
        }

        $now = now();

        if ($now->lessThan($start)) {
            return 0;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            return 100;
        }

        $totalSeconds = $start->diffInSeconds($end);

        if ($totalSeconds <= 0) {
            return 100;
        }

        $elapsedSeconds = $start->diffInSeconds($now);
        $percentage = (int) round(($elapsedSeconds / $totalSeconds) * 100);

        return min(max($percentage, 0), 100);
    }
}
