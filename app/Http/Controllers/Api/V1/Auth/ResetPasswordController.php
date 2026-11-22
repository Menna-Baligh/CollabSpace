<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends Controller
{
    /**
     * Reset the user's password using the token.
     */
    public function __invoke(ResetPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        $status = $service->resetPassword($request->validated());

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(
                null,
                __('auth.password_reset_successfully')
            );
        }

        return $this->failureResponse(
            __('auth.invalid_or_expired_token'),
            'invalid_reset_token',
            ['email' => [__('auth.invalid_or_expired_token')]],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
