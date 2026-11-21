<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class GoogleAccountLinkController extends Controller
{
    /**
     * Start the Google linking OAuth flow for an authenticated user.
     */
    public function redirect(Request $request, GoogleOAuthService $googleOAuthService): RedirectResponse
    {
        $user = $request->user();
        $state = $googleOAuthService->generateState('link', $user->id);
        $redirectUrl = config('services.google.link_redirect', config('app.url').'/api/v1/auth/google/link/callback');

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver
            ->stateless()
            ->redirectUrl($redirectUrl)
            ->with(['state' => $state])
            ->redirect();
    }

    /**
     * Handle the callback for Google account linking.
     */
    public function callback(Request $request, GoogleOAuthService $googleOAuthService): RedirectResponse
    {
        $frontendLinkUrl = config('services.frontend.link_callback_url', 'http://localhost:3000/settings/connections');

        $state = (string) $request->query('state');
        $code = (string) $request->query('code');

        if (empty($state) || empty($code)) {
            return redirect()->to($frontendLinkUrl.'#error=invalid_oauth_state');
        }

        $cachedState = $googleOAuthService->consumeState($state);

        if (! $cachedState || $cachedState['action'] !== 'link' || empty($cachedState['user_id'])) {
            return redirect()->to($frontendLinkUrl.'#error=invalid_oauth_state');
        }

        $user = User::find($cachedState['user_id']);

        if (! $user || $user->isSuspended()) {
            return redirect()->to($frontendLinkUrl.'#error=account_suspended');
        }

        try {
            $redirectUrl = config('services.google.link_redirect', config('app.url').'/api/v1/auth/google/link/callback');

            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->redirectUrl($redirectUrl)->user();

            $result = $googleOAuthService->handleLinkCallback($user, $googleUser);

            if (isset($result['error'])) {
                return redirect()->to($frontendLinkUrl.'#error='.$result['error']);
            }

            return redirect()->to($frontendLinkUrl.'#status='.($result['status'] ?? 'linked_success'));
        } catch (Throwable) {
            return redirect()->to($frontendLinkUrl.'#error=oauth_failed');
        }
    }
}
