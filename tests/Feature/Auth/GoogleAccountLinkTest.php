<?php

use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\GoogleOAuthService;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

test('authenticated user can initiate google link redirect', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->get('/api/v1/auth/google/link/redirect');

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('accounts.google.com')
        ->and($targetUrl)->toContain('state=');
});

test('unauthenticated user cannot initiate google link redirect', function () {
    $response = $this->getJson('/api/v1/auth/google/link/redirect');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('google link callback successfully links google account using server-side state', function () {
    $user = User::factory()->create();

    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('link', $user->id);

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('google-unique-id');
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/link/callback?state={$state}&code=sample_code");

    $response->assertRedirect(config('services.frontend.link_callback_url').'#status=linked_success');

    $identity = OAuthIdentity::where('user_id', $user->id)->first();
    expect($identity)->not->toBeNull()
        ->and($identity->provider)->toBe('google')
        ->and($identity->provider_id)->toBe('google-unique-id');
});

test('linking a google identity already owned by another user fails', function () {
    $user1 = User::factory()->create();
    OAuthIdentity::create([
        'user_id' => $user1->id,
        'provider' => 'google',
        'provider_id' => 'shared-google-id',
    ]);

    $user2 = User::factory()->create();

    $service = app(GoogleOAuthService::class);
    $state = $service->generateState('link', $user2->id);

    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getId')->andReturn('shared-google-id');
    $googleUser->shouldReceive('getRaw')->andReturn(['email_verified' => true]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get("/api/v1/auth/google/link/callback?state={$state}&code=sample_code");

    $response->assertRedirect(config('services.frontend.link_callback_url').'#error=oauth_provider_already_linked');
});

test('linking callback fails if state is invalid or expired', function () {
    $response = $this->get('/api/v1/auth/google/link/callback?state=tampered_state&code=sample_code');

    $response->assertRedirect(config('services.frontend.link_callback_url').'#error=invalid_oauth_state');
});
