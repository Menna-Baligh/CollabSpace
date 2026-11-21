<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a standardized successful JSON API response.
     *
     * @param  mixed  $data  Response payload
     * @param  string|null  $message  Optional success message placed in meta
     * @param  array<string, mixed>  $meta  Optional metadata
     * @param  int  $status  HTTP status code (default: 200)
     */
    public function successResponse(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $status = Response::HTTP_OK
    ): JsonResponse {
        if ($message !== null) {
            $meta['message'] = $message;
        }

        return new JsonResponse([
            'data' => $data,
            'meta' => empty($meta) ? (object) [] : $meta,
        ], $status);
    }

    /**
     * Return a standardized failed JSON API response.
     *
     * @param  string  $message  Human-readable error description
     * @param  string|null  $code  Machine-readable error code (mapped from status if omitted)
     * @param  mixed  $details  Optional validation/error details
     * @param  int  $status  HTTP status code (default: 400)
     */
    public function failureResponse(
        string $message,
        ?string $code = null,
        mixed $details = [],
        int $status = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return new JsonResponse([
            'error' => [
                'code' => $code ?? $this->defaultErrorCodeForStatus($status),
                'message' => $message,
                'details' => empty($details) ? (object) [] : $details,
            ],
        ], $status);
    }

    /**
     * Alias for failureResponse.
     */
    public function errorResponse(
        string $message,
        ?string $code = null,
        mixed $details = [],
        int $status = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return $this->failureResponse($message, $code, $details, $status);
    }

    /**
     * Map HTTP status codes to standardized error codes per CLAUDE.md.
     */
    protected function defaultErrorCodeForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_failed',
            Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
            default => 'server_error',
        };
    }
}
