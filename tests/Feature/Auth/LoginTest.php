<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user can log in without remember me and receive a 24-hour token', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('Password@12345'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'Password@12345',
        'remember' => false,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['user', 'token', 'token_type', 'expires_in'],
            'meta' => ['message'],
        ]);

    expect($response->json('data.expires_in'))->toBe(86400)
        ->and($response->json('data.token_type'))->toBe('Bearer');

    $token = $user->tokens()->latest()->first();
    expect((int) round(now()->diffInRealHours($token->expires_at)))->toBe(24);
});

test('user can log in with remember me and receive a 30-day token', function () {
    $user = User::factory()->create([
        'email' => 'remember@example.com',
        'password' => Hash::make('Password@12345'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'remember@example.com',
        'password' => 'Password@12345',
        'remember' => true,
    ]);

    $response->assertOk();

    expect($response->json('data.expires_in'))->toBe(2592000);

    $token = $user->tokens()->latest()->first();
    expect((int) round(now()->diffInRealDays($token->expires_at)))->toBe(30);
});

test('expired token is strictly rejected by protected route', function () {
    $user = User::factory()->create();
    // Issue token expired in the past
    $expiredToken = $user->createToken('expired_token', ['*'], now()->subHour())->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$expiredToken)
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('login fails with generic 401 when email does not exist', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'Password@12345',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

test('login fails with generic 401 when password is wrong', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('Password@12345'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'WrongPassword#123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

test('login fails with generic 401 for Google-only account without local password', function () {
    User::factory()->withoutPassword()->create([
        'email' => 'googleonly@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'googleonly@example.com',
        'password' => 'Password@12345',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

test('login fails with generic 401 for suspended account', function () {
    User::factory()->suspended()->create([
        'email' => 'suspended@example.com',
        'password' => Hash::make('Password@12345'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'suspended@example.com',
        'password' => 'Password@12345',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

test('login validation fails with missing fields', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($response->json('error.details'))->toHaveKeys(['email', 'password']);
});

test('login endpoint is rate limited after 5 failed attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'WrongPassword#123',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ratelimit@example.com',
        'password' => 'WrongPassword#123',
    ]);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertHeader('Retry-After');
});
