<?php

use App\Models\User;

test('authenticated user can view their own profile', function () {
    $user = User::factory()->create([
        'name' => 'Alice Wonder',
        'email' => 'alice@example.com',
    ]);

    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Alice Wonder')
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonPath('data.is_google_linked', false);
});

test('unauthenticated user cannot view profile', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('suspended user with valid token is rejected with 403 account_suspended', function () {
    $user = User::factory()->suspended()->create();
    $token = $user->createToken('active_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'account_suspended');
});

test('logout revokes only the current personal access token while leaving other device tokens active', function () {
    $user = User::factory()->create();

    // Two active devices
    $device1Token = $user->createToken('mobile')->plainTextToken;
    $device2Token = $user->createToken('laptop')->plainTextToken;

    expect($user->tokens()->count())->toBe(2);

    // Logout from device 1
    $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$device1Token)
        ->postJson('/api/v1/auth/logout');

    $logoutResponse->assertOk()
        ->assertJsonPath('meta.message', __('auth.logout_successfully'));

    $this->app['auth']->forgetGuards();

    // Device 1 token is revoked and cannot access /me
    $checkDevice1 = $this->withHeader('Authorization', 'Bearer '.$device1Token)
        ->getJson('/api/v1/auth/me');
    $checkDevice1->assertStatus(401);

    // Device 2 token is still active and can access /me
    $checkDevice2 = $this->withHeader('Authorization', 'Bearer '.$device2Token)
        ->getJson('/api/v1/auth/me');
    $checkDevice2->assertOk()
        ->assertJsonPath('data.id', $user->id);

    expect($user->tokens()->count())->toBe(1);
});
