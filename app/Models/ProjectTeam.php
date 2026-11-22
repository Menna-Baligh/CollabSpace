<?php

namespace App\Models;

use Database\Factories\ProjectTeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectTeam extends Model
{
    /** @use HasFactory<ProjectTeamFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_teams';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'color',
        'leader_id',
    ];

    /**
     * The project this team belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The leader of this team.
     *
     * @return BelongsTo<User, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Team members.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_team_user')
            ->withPivot('role_title')
            ->withTimestamps();
    }

    /**
     * Check if a given user is a member of this team.
     */
    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->members()->where('users.id', $userId)->exists();
    }

    /**
     * Check if a given user is the leader of this team.
     */
    public function isLeader(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return (int) $this->leader_id === (int) $userId;
    }
}
