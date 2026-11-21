<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    /**
     * Send a password reset link to the given user.
     * Always returns generic response to prevent account enumeration.
     */
    public function __invoke(ForgotPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        $service->sendResetLink($request->validated('email'));

        return $this->successResponse(
            null,
            __('auth.password_reset_link_sent')
        );
    }
}
