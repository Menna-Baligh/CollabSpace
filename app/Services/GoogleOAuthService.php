<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleOAuthService
{
    /**
     * Lifetime for OAuth state and exchange code in seconds (5 minutes).
     */
    public const STATE_TTL = 300;

    /**
     * Generate and cache a secure OAuth state token for login/signup.
     */
    public function generateState(): string
    {
        $state = Str::random(40);
        $key = "auth:oauth_state:{$state}";

        Cache::put($key, [
            'action' => 'login',
        ], self::STATE_TTL);

        return $state;
    }

    /**
     * Atomically consume an OAuth state token.
     *
     * @return array{action: string}|null
     */
    public function consumeState(string $state): ?array
    {
        $lockKey = "auth:lock:state:{$state}";
        $cacheKey = "auth:oauth_state:{$state}";

        return Cache::lock($lockKey, 10)->block(3, function () use ($cacheKey) {
            $payload = Cache::get($cacheKey);

            if ($payload) {
                Cache::forget($cacheKey);
            }

            return $payload;
        });
    }

    /**
     * Process Google OAuth login/registration callback.
     *
     * @return array{code?: string, error?: string}
     */
    public function handleLoginCallback(SocialiteUser $googleUser): array
    {
        $rawUser = $googleUser->getRaw();
        $isEmailVerified = (bool) ($rawUser['email_verified'] ?? false);

        if (! $isEmailVerified) {
            return ['error' => 'oauth_unverified_email'];
        }

        $googleId = (string) $googleUser->getId();
        $email = strtolower(trim((string) $googleUser->getEmail()));

        // Case 1: Google identity already linked to a CollabSpace user
        $identity = OAuthIdentity::where('provider', 'google')
            ->where('provider_id', $googleId)
            ->with('user')
            ->first();

        if ($identity) {
            $user = $identity->user;

            if ($user->isSuspended()) {
                return ['error' => 'account_suspended'];
            }

            $code = $this->generateExchangeCode($user);

            return ['code' => $code];
        }

        // Case 2: Google identity NOT linked
        // Check if an existing CollabSpace account exists with this email
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            // Mandatory protection: reject automatic linking to existing email/password account
            return ['error' => 'oauth_account_exists_unlinked'];
        }

        // Create new Google-only user
        $newUser = DB::transaction(function () use ($googleUser, $email, $googleId) {
            $user = new User([
                'name' => $googleUser->getName() ?: 'User',
                'email' => $email,
                'password' => null,
            ]);
            $user->system_role = SystemRole::USER;
            $user->email_verified_at = now();
            $user->save();

            OAuthIdentity::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_id' => $googleId,
            ]);

            return $user;
        });

        $code = $this->generateExchangeCode($newUser);

        return ['code' => $code];
    }

    /**
     * Generate and cache a one-time exchange code.
     */
    public function generateExchangeCode(User $user): string
    {
        $rawCode = Str::random(64);
        $hashedCode = hash('sha256', $rawCode);
        $key = "auth:exchange_code:{$hashedCode}";

        Cache::put($key, [
            'user_id' => $user->id,
        ], self::STATE_TTL);

        return $rawCode;
    }

    /**
     * Atomically exchange a one-time code for a Sanctum Personal Access Token.
     *
     * @return array{user: User, token: string, token_type: string, expires_in: int}|null
     */
    public function exchangeCode(string $rawCode): ?array
    {
        $hashedCode = hash('sha256', $rawCode);
        $lockKey = "auth:lock:exchange:{$hashedCode}";
        $cacheKey = "auth:exchange_code:{$hashedCode}";

        $payload = Cache::lock($lockKey, 10)->block(3, function () use ($cacheKey) {
            $data = Cache::get($cacheKey);

            if ($data) {
                Cache::forget($cacheKey);
            }

            return $data;
        });

        if (! $payload) {
            return null;
        }

        $user = User::find($payload['user_id']);

        if (! $user || $user->isSuspended()) {
            return null;
        }

        $expiresAt = now()->addHours(24);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => AuthService::DEFAULT_TOKEN_LIFETIME,
        ];
    }
}
