<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Handle user login and issue a token based on Remember Me selection.
     */
    public function __invoke(LoginRequest $request, AuthService $authService): JsonResponse
    {
        $result = $authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember', false)
        );

        if (! $result) {
            return $this->failureResponse(
                __('auth.failed'),
                'invalid_credentials',
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
