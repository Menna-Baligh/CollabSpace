<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleExchangeRequest;
use App\Http\Resources\UserResource;
use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Return the Google OAuth authorization URL as JSON so the frontend can redirect the user.
     */
    public function redirect(GoogleOAuthService $googleOAuthService): JsonResponse
    {
        $state = $googleOAuthService->generateState();

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        $url = $driver
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return $this->successResponse(['url' => $url]);
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request, GoogleOAuthService $googleOAuthService): RedirectResponse
    {
        $frontendCallbackUrl = config('services.frontend.auth_callback_url', 'http://localhost:3000/auth/callback');

        $state = (string) $request->query('state');
        $code = (string) $request->query('code');

        if (empty($state) || empty($code)) {
            return redirect()->to($frontendCallbackUrl.'#error=invalid_oauth_state');
        }

        $cachedState = $googleOAuthService->consumeState($state);

        if (! $cachedState || $cachedState['action'] !== 'login') {
            return redirect()->to($frontendCallbackUrl.'#error=invalid_oauth_state');
        }

        try {
            /** @var GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();

            $result = $googleOAuthService->handleLoginCallback($googleUser);

            if (isset($result['error'])) {
                return redirect()->to($frontendCallbackUrl.'#error='.$result['error']);
            }

            return redirect()->to($frontendCallbackUrl.'#code='.$result['code']);
        } catch (Throwable) {
            return redirect()->to($frontendCallbackUrl.'#error=oauth_failed');
        }
    }

    /**
     * Exchange a temporary one-time code for a Sanctum Personal Access Token.
     */
    public function exchange(GoogleExchangeRequest $request, GoogleOAuthService $googleOAuthService): JsonResponse
    {
        $result = $googleOAuthService->exchangeCode($request->validated('code'));

        if (! $result) {
            return $this->failureResponse(
                __('auth.invalid_exchange_code'),
                'invalid_exchange_code',
                status: Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ], __('auth.login_successfully'));
    }
}
