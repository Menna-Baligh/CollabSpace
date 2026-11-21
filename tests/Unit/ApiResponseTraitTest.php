<?php

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

// Create an anonymous class or dummy class using the trait
$testClass = new class
{
    use ApiResponse;
};

test('successResponse returns standardized structure with default 200 status', function () use ($testClass) {
    $response = $testClass->successResponse(['id' => 1]);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = $response->getData(true);
    expect($data)->toHaveKeys(['data', 'meta'])
        ->and($data['data'])->toBe(['id' => 1])
        ->and($data['meta'])->toBe([]);
});

test('successResponse includes message inside meta and supports custom status', function () use ($testClass) {
    $response = $testClass->successResponse(
        ['id' => 2],
        message: 'Resource created successfully.',
        meta: ['page' => 1],
        status: Response::HTTP_CREATED
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED);

    $data = $response->getData(true);
    expect($data['data'])->toBe(['id' => 2])
        ->and($data['meta']['message'])->toBe('Resource created successfully.')
        ->and($data['meta']['page'])->toBe(1);
});

test('failureResponse returns standardized error envelope with default 400 status and mapped code', function () use ($testClass) {
    $response = $testClass->failureResponse('Something went wrong.');

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);

    $data = $response->getData(true);
    expect($data)->toHaveKey('error')
        ->and($data['error']['code'])->toBe('bad_request')
        ->and($data['error']['message'])->toBe('Something went wrong.')
        ->and($data['error']['details'])->toBe([]);
});

test('failureResponse supports custom code, details, and HTTP status', function () use ($testClass) {
    $response = $testClass->failureResponse(
        message: 'Invalid credentials provided.',
        code: 'invalid_credentials',
        details: ['field' => ['The credential is invalid.']],
        status: Response::HTTP_UNAUTHORIZED
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);

    $data = $response->getData(true);
    expect($data['error']['code'])->toBe('invalid_credentials')
        ->and($data['error']['message'])->toBe('Invalid credentials provided.')
        ->and($data['error']['details'])->toBe(['field' => ['The credential is invalid.']]);
});

test('errorResponse alias works identically to failureResponse', function () use ($testClass) {
    $response = $testClass->errorResponse(
        message: 'Unprocessable entity.',
        code: 'validation_failed',
        status: Response::HTTP_UNPROCESSABLE_ENTITY
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);

    $data = $response->getData(true);
    expect($data['error']['code'])->toBe('validation_failed')
        ->and($data['error']['message'])->toBe('Unprocessable entity.');
});

