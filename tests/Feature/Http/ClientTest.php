<?php

declare(strict_types=1);

use Clinically\Halaxy\Exceptions\AuthenticationException;
use Clinically\Halaxy\Exceptions\ForbiddenException;
use Clinically\Halaxy\Exceptions\HalaxyException;
use Clinically\Halaxy\Exceptions\MethodNotAllowedException;
use Clinically\Halaxy\Exceptions\NotFoundException;
use Clinically\Halaxy\Exceptions\RateLimitException;
use Clinically\Halaxy\Exceptions\ServerException;
use Clinically\Halaxy\Exceptions\ValidationException;
use Clinically\Halaxy\Halaxy;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);
});

test('throws AuthenticationException on 401 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/123' => Http::response([
            'error' => 'invalid_token',
            'error_description' => 'The access token is invalid',
        ], 401),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->find('123'))
        ->toThrow(AuthenticationException::class);
});

test('throws ForbiddenException on 403 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/123' => Http::response([
            'resourceType' => 'OperationOutcome',
            'issue' => [['severity' => 'error', 'code' => 'forbidden']],
        ], 403),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->find('123'))
        ->toThrow(ForbiddenException::class);
});

test('throws NotFoundException on 404 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/99999' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/operation-outcome-not-found.json'), true),
            404,
        ),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->find('99999'))
        ->toThrow(NotFoundException::class);
});

test('NotFoundException includes operation outcome details', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/99999' => Http::response([
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => 'Patient/99999 not found',
                ],
            ],
        ], 404),
    ]);

    $halaxy = app(Halaxy::class);

    try {
        $halaxy->patients()->find('99999');
        $this->fail('Expected NotFoundException');
    } catch (NotFoundException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($e->getMessage())->toContain('not found')
            ->and($e->getOperationOutcome())->toBeArray();
    }
});

test('throws ValidationException on 422 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/operation-outcome-error.json'), true),
            422,
        ),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->create(['name' => []]))
        ->toThrow(ValidationException::class);
});

test('throws ValidationException on 400 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response([
            'resourceType' => 'OperationOutcome',
            'issue' => [
                [
                    'severity' => 'error',
                    'code' => 'invalid',
                    'diagnostics' => 'Invalid request',
                ],
            ],
        ], 400),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->create([]))
        ->toThrow(ValidationException::class);
});

test('throws RateLimitException on 429 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response([
            'error' => 'rate_limit_exceeded',
        ], 429, ['Retry-After' => '60']),
    ]);

    $halaxy = app(Halaxy::class);

    try {
        $halaxy->patients()->list();
        $this->fail('Expected RateLimitException');
    } catch (RateLimitException $e) {
        expect($e->getStatusCode())->toBe(429)
            ->and($e->retryAfter)->toBe(60);
    }
});

test('throws MethodNotAllowedException on 405 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner/PR-1' => Http::response(null, 405, ['Allow' => 'GET, POST']),
    ]);

    $halaxy = app(Halaxy::class);

    try {
        $halaxy->getClient()->patch('Practitioner/PR-1', ['resourceType' => 'Practitioner']);
        $this->fail('Expected MethodNotAllowedException');
    } catch (MethodNotAllowedException $e) {
        // Halaxy sends no body with a 405, so the message has to be built from
        // the request — otherwise this reads as "An unknown error occurred".
        expect($e->getStatusCode())->toBe(405)
            ->and($e->getRequestMethod())->toBe('PATCH')
            ->and($e->getMessage())->toContain('does not support PATCH')
            ->and($e->getMessage())->toContain('Practitioner/PR-1')
            ->and($e->getMessage())->toContain('Allowed methods: GET, POST')
            ->and($e->isRetryable())->toBeFalse();
    }
});

test('marks 4xx as non-retryable and 5xx, 408, 429 as retryable', function (int $status, bool $retryable): void {
    expect((new HalaxyException('nope', $status))->isRetryable())->toBe($retryable);
})->with([
    'bad request' => [400, false],
    'unauthorized' => [401, false],
    'forbidden' => [403, false],
    'not found' => [404, false],
    'method not allowed' => [405, false],
    'request timeout' => [408, true],
    'unprocessable' => [422, false],
    'rate limited' => [429, true],
    'server error' => [500, true],
    'not implemented' => [501, false],
    'unavailable' => [503, true],
]);

test('treats a status-less failure as retryable', function (): void {
    expect((new HalaxyException('connection reset'))->isRetryable())->toBeTrue();
});

test('throws ServerException on 500 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response([
            'error' => 'internal_server_error',
        ], 500),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->list())
        ->toThrow(ServerException::class);
});

test('throws ServerException on 503 response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response([
            'error' => 'service_unavailable',
        ], 503),
    ]);

    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->patients()->list())
        ->toThrow(ServerException::class);
});

test('sets correct content type for requests', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/123' => Http::response(['resourceType' => 'Patient', 'id' => '123']),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->patients()->find('123');

    Http::assertSent(function ($request) {
        // Skip the OAuth token request
        if (str_contains($request->url(), 'oauth')) {
            return true;
        }

        return $request->hasHeader('Accept', 'application/fhir+json')
            && $request->hasHeader('Content-Type', 'application/fhir+json');
    });
});

test('sets authorization header with bearer token', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'my-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/123' => Http::response(['resourceType' => 'Patient', 'id' => '123']),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->patients()->find('123');

    Http::assertSent(function ($request) {
        // Skip the OAuth token request
        if (str_contains($request->url(), 'oauth')) {
            return true;
        }

        return $request->hasHeader('Authorization', 'Bearer my-access-token');
    });
});

test('uses correct base URL for AU region', function (): void {
    config(['halaxy.region' => 'au']);

    Http::fake([
        'https://au-api.halaxy.com/*' => Http::response(['resourceType' => 'Patient', 'id' => '123']),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->patients()->find('123');

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://au-api.halaxy.com/');
    });
});

test('uses correct base URL for EU region', function (): void {
    config(['halaxy.region' => 'eu']);

    // Need to rebind the Halaxy instance with new config
    app()->forgetInstance(Halaxy::class);

    Http::fake([
        'https://eu-api.halaxy.com/*' => Http::response(['resourceType' => 'Patient', 'id' => '123']),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->patients()->find('123');

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://eu-api.halaxy.com/');
    });
});
