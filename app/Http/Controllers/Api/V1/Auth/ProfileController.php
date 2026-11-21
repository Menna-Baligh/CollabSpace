<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Return the currently authenticated user's profile.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user())
        );
    }
}
