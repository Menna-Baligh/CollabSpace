<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\SystemRole;
use App\Enums\UserAvailabilityStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'system_role',
        'suspended_at',
        'avatar',
        'phone',
        'availability_status',
        'job_title',
        'years_of_experience',
        'bio',
        'hours_worked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'system_role' => SystemRole::class,
            'suspended_at' => 'datetime',
            'availability_status' => UserAvailabilityStatus::class,
            'years_of_experience' => 'integer',
            'hours_worked' => 'integer',
        ];
    }

    /**
     * Determine if the user's account is suspended.
     */
    public function isSuspended(): bool
    {
        return ! is_null($this->suspended_at);
    }

    /**
     * Determine if the user has a locally configured password.
     */
    public function hasPassword(): bool
    {
        return ! is_null($this->password);
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->system_role === SystemRole::ADMIN;
    }

    /**
     * Get the OAuth identities associated with the user.
     *
     * @return HasMany<OAuthIdentity, $this>
     */
    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OAuthIdentity::class);
    }

    /**
     * Check if user is linked to a specific OAuth provider.
     */
    public function isLinkedTo(string $provider): bool
    {
        if ($this->relationLoaded('oauthIdentities')) {
            return $this->oauthIdentities->contains('provider', $provider);
        }

        return $this->oauthIdentities()->where('provider', $provider)->exists();
    }

    /**
     * Projects associated with the user.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Project teams associated with the user.
     *
     * @return BelongsToMany<ProjectTeam, $this>
     */
    public function projectTeams(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTeam::class, 'project_team_user')
            ->withPivot('role_title')
            ->withTimestamps();
    }

    /**
     * Tasks assigned to the user.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->withTimestamps();
    }

    /**
     * Tasks reviewed by the user.
     *
     * @return HasMany<Task, $this>
     */
    public function reviewedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'reviewer_id');
    }

    /**
     * Tasks created by the user.
     *
     * @return HasMany<Task, $this>
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Files uploaded by the user.
     *
     * @return HasMany<ProjectFile, $this>
     */
    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'uploaded_by');
    }

    /**
     * Activities performed by the user.
     *
     * @return HasMany<UserActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Reports submitted by the user.
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get avatar public URL if avatar is set.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    /**
     * Check if the user is a Project Manager of a given project.
     */
    public function isProjectManagerOf(Project|int $project): bool
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        return $this->projects()
            ->where('projects.id', $projectId)
            ->wherePivot('role', ProjectRole::PROJECT_MANAGER->value)
            ->exists();
    }

    /**
     * Check if the user is a member of a given project.
     */
    public function isMemberOfProject(Project|int $project): bool
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        return $this->projects()
            ->where('projects.id', $projectId)
            ->exists();
    }
}
