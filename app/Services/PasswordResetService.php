<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    /**
     * Send password reset link to user if exists.
     * Always returns true to prevent account enumeration.
     */
    public function sendResetLink(string $email): bool
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        if ($user && ! $user->isSuspended()) {
            $token = $this->broker()->createToken($user);
            $user->notify(new ResetPasswordNotification($token, $user->email));
        }

        return true;
    }

    /**
     * Reset password using provided token and credentials.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function resetPassword(array $credentials): string
    {
        return $this->broker()->reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            // Revoke all existing Sanctum Personal Access Tokens across all devices
            $user->tokens()->delete();
        });
    }

    /**
     * Get the password broker instance.
     */
    protected function broker(): PasswordBroker
    {
        return Password::broker();
    }
}
