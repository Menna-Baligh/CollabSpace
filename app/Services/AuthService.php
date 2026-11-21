<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Lifetime for standard tokens in seconds (24 hours).
     */
    public const DEFAULT_TOKEN_LIFETIME = 86400;

    /**
     * Lifetime for remember-me tokens in seconds (30 days).
     */
    public const REMEMBER_TOKEN_LIFETIME = 2592000;

    /**
     * Register a new user and issue an immediate 24-hour Sanctum token.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string, token_type: string, expires_in: int}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
            ]);
            $user->system_role = SystemRole::USER;
            $user->save();
            $user->setRelation('oauthIdentities', collect());
            $expiresAt = now()->addHours(24);
            $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => self::DEFAULT_TOKEN_LIFETIME,
            ];
        });
    }

    /**
     * Authenticate user with email and password and issue a token based on remember-me.
     *
     * @return array{user: User, token: string, token_type: string, expires_in: int}|null
     */
    public function login(string $email, string $password, bool $remember = false): ?array
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        // Safe rejection: non-existent email, no local password, or hash mismatch
        if (! $user || ! $user->hasPassword() || ! Hash::check($password, $user->password)) {
            return null;
        }

        // Suspended accounts must not receive tokens; generic rejection to prevent enumeration
        if ($user->isSuspended()) {
            return null;
        }

        $expiresAt = $remember ? now()->addDays(30) : now()->addHours(24);
        $expiresIn = $remember ? self::REMEMBER_TOKEN_LIFETIME : self::DEFAULT_TOKEN_LIFETIME;

        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;
        $user->loadMissing('oauthIdentities');

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * Revoke only the currently authenticated Personal Access Token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
