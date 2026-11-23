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

    $response = $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'member@example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('auth.password_reset_link_sent'));

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('forgot password returns generic response without enumeration for unknown email', function () {
    Notification::fake();

    $response = $this->postJson(route('api.v1.auth.forgot-password'), [
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

    $response = $this->postJson(route('api.v1.auth.reset-password'), [
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
        ->getJson(route('api.v1.auth.me'));
    $checkAuth->assertStatus(401);
});

test('reset password fails if token was already used', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $resetToken = Password::broker()->createToken($user);

    // First reset: success
    $this->postJson(route('api.v1.auth.reset-password'), [
        'email' => 'user@example.com',
        'token' => $resetToken,
        'password' => 'NewPassWord#123',
        'password_confirmation' => 'NewPassWord#123',
    ])->assertOk();

    // Second attempt with same token: rejected
    $replayResponse = $this->postJson(route('api.v1.auth.reset-password'), [
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

    $response = $this->postJson(route('api.v1.auth.reset-password'), [
        'email' => 'user@example.com',
        'token' => $resetToken,
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($response->json('error.details'))->toHaveKey('password');
});

test('forgot password normalizes email casing before lookup', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'member@example.com']);

    // Send with uppercase characters — should still match and send notification
    $response = $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'MEMBER@EXAMPLE.COM',
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('auth.password_reset_link_sent'));

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('reset password normalizes email casing before token lookup', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('OldPassword#123'),
    ]);

    $resetToken = Password::broker()->createToken($user);

    // Submit with mixed-case email — the normalized value must match the stored token
    $response = $this->postJson(route('api.v1.auth.reset-password'), [
        'email' => 'USER@EXAMPLE.COM',
        'token' => $resetToken,
        'password' => 'NewPassWord#2026',
        'password_confirmation' => 'NewPassWord#2026',
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.message', __('auth.password_reset_successfully'));
});

test('reset password is rejected for suspended accounts without leaking account status', function () {
    $user = User::factory()->suspended()->create([
        'email' => 'suspended@example.com',
    ]);

    $resetToken = Password::broker()->createToken($user);

    $response = $this->postJson(route('api.v1.auth.reset-password'), [
        'email' => 'suspended@example.com',
        'token' => $resetToken,
        'password' => 'BrandNew#2026',
        'password_confirmation' => 'BrandNew#2026',
    ]);

    // Returns 422 with the same generic error code as an invalid/expired token
    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_reset_token');

    // Password must NOT have changed
    $user->refresh();
    expect(Hash::check('BrandNew#2026', $user->password))->toBeFalse();
});
