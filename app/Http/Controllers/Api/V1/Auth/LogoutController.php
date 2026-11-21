<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Handle user logout and revoke only the current Personal Access Token.
     */
    public function __invoke(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request->user());

        return $this->successResponse(
            null,
            __('auth.logout_successfully')
        );
    }
}
