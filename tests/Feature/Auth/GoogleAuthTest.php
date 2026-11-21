<?php

use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\GoogleOAuthService;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

test('google redirect endpoint initiates OAuth and redirects to Google with state', function () {
    $response = $this->get('/api/v1/auth/google/redirect');

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('accounts.google.com')
        ->and($targetUrl)->toContain('state=');
});

test('google callback with invalid or missing state redirects to frontend with error', function () {
    $response = $this->get('/api/v1/auth/google/callback?state=invalid_state&code=test_code');

    $response->assertRedirect(config('services.frontend.auth_callback_url').'#error=invalid_oauth_state');
});

test('google callback with unverified google email redirects with error', function () {
    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('login');

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => false]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/callback?state={$state}&code=sample_code");

    $response->assertRedirect(config('services.frontend.auth_callback_url').'#error=oauth_unverified_email');
});

test('google callback for existing unlinked email account rejects login to prevent takeover', function () {
    // Existing user with email/password
    User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('login');

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-id-123');
    $googleUser->shouldReceive('getEmail')->andReturn('existing@example.com');
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/callback?state={$state}&code=sample_code");

    $response->assertRedirect(config('services.frontend.auth_callback_url').'#error=oauth_account_exists_unlinked');
});

test('google callback for brand new user creates account with null password and generates exchange code', function () {
    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('login');

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-sub-456');
    $googleUser->shouldReceive('getName')->andReturn('New Google User');
    $googleUser->shouldReceive('getEmail')->andReturn('newgoogle@example.com');
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/callback?state={$state}&code=sample_code");

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('#code=');

    $user = User::where('email', 'newgoogle@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasPassword())->toBeFalse()
        ->and($user->system_role->value)->toBe('user');

    $identity = OAuthIdentity::where('user_id', $user->id)->first();
    expect($identity)->not->toBeNull()
        ->and($identity->provider)->toBe('google')
        ->and($identity->provider_id)->toBe('google-sub-456');
});

test('google callback for already linked active user generates exchange code', function () {
    $user = User::factory()->create();
    OAuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'existing-google-id',
    ]);

    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('login');

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('existing-google-id');
    $googleUser->shouldReceive('getEmail')->andReturn($user->email);
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/callback?state={$state}&code=sample_code");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('#code=');
});

test('google callback for already linked suspended user is rejected', function () {
    $user = User::factory()->suspended()->create();
    OAuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'suspended-google-id',
    ]);

    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('login');

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('suspended-google-id');
    $googleUser->shouldReceive('getEmail')->andReturn($user->email);
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/callback?state={$state}&code=sample_code");

    $response->assertRedirect(config('services.frontend.auth_callback_url').'#error=account_suspended');
});

test('exchange endpoint exchanges valid code for a 24-hour Bearer token', function () {
    $user = User::factory()->create();
    $service = app(GoogleOAuthService::class);
    $code = $service->generateExchangeCode($user);

    $response = $this->postJson('/api/v1/auth/google/exchange', [
        'code' => $code,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email'],
                'token',
                'token_type',
                'expires_in',
            ],
            'meta' => ['message'],
        ]);

    expect($response->json('data.expires_in'))->toBe(86400)
        ->and($response->json('data.token_type'))->toBe('Bearer');
});

test('exchange endpoint rejects replaying or reusing the same code', function () {
    $user = User::factory()->create();
    $service = app(GoogleOAuthService::class);
    $code = $service->generateExchangeCode($user);

    // First use: success
    $firstResponse = $this->postJson('/api/v1/auth/google/exchange', ['code' => $code]);
    $firstResponse->assertOk();

    // Replay attempt: rejected
    $replayResponse = $this->postJson('/api/v1/auth/google/exchange', ['code' => $code]);
    $replayResponse->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_exchange_code');
});

test('exchange endpoint rejects invalid or expired code', function () {
    $response = $this->postJson('/api/v1/auth/google/exchange', [
        'code' => 'nonexistent_code',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_exchange_code');
});
