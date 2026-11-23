<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a new user can register successfully and receive a 24-hour token', function () {
    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'SecurePass#2026',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'is_google_linked', 'created_at', 'updated_at'],
                'token',
                'token_type',
                'expires_in',
            ],
            'meta' => ['message'],
        ]);

    expect($response->json('data.token_type'))->toBe('Bearer')
        ->and($response->json('data.expires_in'))->toBe(86400)
        ->and($response->json('data.user.email'))->toBe('john@example.com')
        ->and($response->json('data.user.name'))->toBe('John Doe')
        ->and($response->json('data.user.is_google_linked'))->toBeFalse();

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('SecurePass#2026', $user->password))->toBeTrue()
        ->and($user->system_role)->toBe(SystemRole::USER);

    // Verify token expiration is 24 hours
    $token = $user->tokens()->first();
    expect($token)->not->toBeNull()
        ->and($token->expires_at)->not->toBeNull()
        ->and((int) round(now()->diffInRealHours($token->expires_at)))->toBe(24);
});

test('registration ignores client-supplied system_role and always assigns user role', function () {
    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Hacker Admin',
        'email' => 'hacker@example.com',
        'password' => 'SecurePass#2026',
        'system_role' => 'admin',
    ]);

    $response->assertCreated();

    $user = User::where('email', 'hacker@example.com')->first();
    expect($user->system_role)->toBe(SystemRole::USER);
});

test('registration does not require password_confirmation', function () {
    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass#2026',
    ]);

    $response->assertCreated();
});

test('registration fails when required fields are missing', function () {
    $response = $this->postJson(route('api.v1.auth.register'), []);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($response->json('error.details'))->toHaveKeys(['name', 'email', 'password']);
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Test User',
        'email' => 'existing@example.com',
        'password' => 'SecurePass#2026',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($response->json('error.details'))->toHaveKey('email');
});

test('registration fails when password violates policy', function (string $weakPassword) {
    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => $weakPassword,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($response->json('error.details'))->toHaveKey('password');
})->with([
    'less than 12 characters' => 'Short#1a',
    'missing uppercase' => 'lowercase#12345',
    'missing lowercase' => 'UPPERCASE#12345',
    'missing number' => 'NoNumberSymbol!',
    'missing special symbol' => 'NoSpecialSymbol123',
]);

test('registration returns localized Arabic response when requested', function () {
    $response = $this->withHeader('Accept-Language', 'ar')
        ->postJson(route('api.v1.auth.register'), [
            'name' => 'أحمد علي',
            'email' => 'ahmed@example.com',
            'password' => 'SecurePass#2026',
        ]);

    $response->assertCreated()
        ->assertJsonPath('meta.message', 'تم التسجيل بنجاح.');
});
