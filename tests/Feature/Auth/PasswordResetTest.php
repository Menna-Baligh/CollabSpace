<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('forgot password sends notification for registered user', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'member@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('auth.password_reset_link_sent'));

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('forgot password returns generic response without enumeration for unknown email', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'unknown@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('auth.password_reset_link_sent'));

    Notification::assertNothingSent();
});

test('reset password successfully updates password and revokes all active tokens', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('OldPassword#123'),
    ]);

    // Create active personal access tokens (e.g. mobile and web)
    $token1 = $user->createToken('device1')->plainTextToken;
    $token2 = $user->createToken('device2')->plainTextToken;

    expect($user->tokens()->count())->toBe(2);

    $resetToken = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $resetToken,
        'password' => 'BrandNewPass#2026',
        'password_confirmation' => 'BrandNewPass#2026',
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('auth.password_reset_successfully'));

    // Check password updated
    $user->refresh();
    expect(Hash::check('BrandNewPass#2026', $user->password))->toBeTrue();

    // Check all previous tokens revoked
    expect($user->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    // Old tokens cannot authenticate
    $checkAuth = $this->withHeader('Authorization', 'Bearer '.$token1)
        ->getJson('/api/v1/auth/me');
    $checkAuth->assertStatus(401);
});

test('reset password fails if token was already used', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $resetToken = Password::broker()->createToken($user);

    // First reset: success
    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $resetToken,
        'password' => 'NewPassWord#123',
        'password_confirmation' => 'NewPassWord#123',
    ])->assertOk();

    // Second attempt with same token: rejected
    $replayResponse = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $resetToken,
        'password' => 'AnotherPass#123',
        'password_confirmation' => 'AnotherPass#123',
    ]);

    $replayResponse->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_reset_token');
});

test('reset password enforces password policy', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $resetToken = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'user@example.com',
        'token' => $resetToken,
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($response->json('error.details'))->toHaveKey('password');
});
