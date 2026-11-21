<?php

namespace App\Models;

use App\Enums\SystemRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
}
