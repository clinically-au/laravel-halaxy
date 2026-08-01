<?php

declare(strict_types=1);

use Clinically\Halaxy\Contracts\AuthenticatorInterface;
use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Exceptions\AuthenticationException;
use Clinically\Halaxy\Http\Authenticator;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;

function createAuthenticator(
    string $clientId = 'test-client-id',
    string $clientSecret = 'test-client-secret',
    Region $region = Region::AU,
    ?CacheRepository $cache = null,
): Authenticator {
    $cache ??= new CacheRepository(new ArrayStore);

    return new Authenticator(
        clientId: $clientId,
        clientSecret: $clientSecret,
        region: $region,
        cache: $cache,
        cachePrefix: 'halaxy',
        tokenBuffer: 60,
    );
}

test('implements AuthenticatorInterface', function (): void {
    $authenticator = createAuthenticator();

    expect($authenticator)->toBeInstanceOf(AuthenticatorInterface::class);
});

test('getAccessToken fetches new token when cache is empty', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $authenticator = createAuthenticator();
    $token = $authenticator->getAccessToken();

    expect($token)->toBe('new-access-token');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'oauth/token')
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'test-client-id'
            && $request['client_secret'] === 'test-client-secret';
    });
});

test('getAccessToken returns cached token when available', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);
    $cache->put('halaxy.token.au', 'cached-token', 3600);

    $authenticator = createAuthenticator(cache: $cache);
    $token = $authenticator->getAccessToken();

    expect($token)->toBe('cached-token');

    // Should not make any HTTP requests
    Http::assertNothingSent();
});

test('refreshToken fetches new token and caches it', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'refreshed-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);
    $authenticator = createAuthenticator(cache: $cache);

    $token = $authenticator->refreshToken();

    expect($token)->toBe('refreshed-token')
        ->and($cache->get('halaxy.token.au'))->toBe('refreshed-token');
});

test('refreshToken calculates TTL with buffer', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 300, // 5 minutes
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);
    $authenticator = createAuthenticator(cache: $cache);

    $authenticator->refreshToken();

    // Token should be cached (300 - 60 buffer = 240 seconds)
    expect($cache->get('halaxy.token.au'))->toBe('test-token');
});

test('refreshToken uses minimum TTL of 60 seconds', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 30, // Less than buffer
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);
    $authenticator = createAuthenticator(cache: $cache);

    $authenticator->refreshToken();

    // Should still cache with minimum TTL
    expect($cache->get('halaxy.token.au'))->toBe('test-token');
});

test('refreshToken defaults to 900 seconds when expires_in is missing', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            // No expires_in
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);
    $authenticator = createAuthenticator(cache: $cache);

    $token = $authenticator->refreshToken();

    expect($token)->toBe('test-token')
        ->and($cache->get('halaxy.token.au'))->toBe('test-token');
});

test('refreshToken throws exception when credentials are missing', function (): void {
    $authenticator = createAuthenticator(clientId: '', clientSecret: '');

    expect(fn () => $authenticator->refreshToken())
        ->toThrow(AuthenticationException::class);
});

test('refreshToken throws exception when client_id is empty', function (): void {
    $authenticator = createAuthenticator(clientId: '');

    expect(fn () => $authenticator->refreshToken())
        ->toThrow(AuthenticationException::class);
});

test('refreshToken throws exception when client_secret is empty', function (): void {
    $authenticator = createAuthenticator(clientSecret: '');

    expect(fn () => $authenticator->refreshToken())
        ->toThrow(AuthenticationException::class);
});

test('refreshToken throws exception on failed HTTP response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Client authentication failed',
        ], 401),
    ]);

    $authenticator = createAuthenticator();

    expect(fn () => $authenticator->refreshToken())
        ->toThrow(AuthenticationException::class);
});

test('refreshToken includes the OAuth error detail in the exception message', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Client authentication failed',
        ], 401),
    ]);

    $authenticator = createAuthenticator();

    expect(fn () => $authenticator->refreshToken())
        ->toThrow(AuthenticationException::class, 'HTTP 401 invalid_client Client authentication failed');
});

test('token request sends a User-Agent header', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $authenticator = new Authenticator(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
        region: Region::AU,
        cache: new CacheRepository(new ArrayStore),
        userAgent: 'Acme Health (dev@acme.test)',
    );

    $authenticator->refreshToken();

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', 'Acme Health (dev@acme.test)'));
});

test('refreshToken throws exception when access_token is missing from response', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'token_type' => 'Bearer',
            'expires_in' => 900,
            // No access_token
        ]),
    ]);

    $authenticator = createAuthenticator();

    expect(fn () => $authenticator->refreshToken())
        ->toThrow(AuthenticationException::class);
});

test('clearToken removes cached token', function (): void {
    $cache = new CacheRepository(new ArrayStore);
    $cache->put('halaxy.token.au', 'cached-token', 3600);

    $authenticator = createAuthenticator(cache: $cache);

    expect($cache->get('halaxy.token.au'))->toBe('cached-token');

    $authenticator->clearToken();

    expect($cache->get('halaxy.token.au'))->toBeNull();
});

test('uses correct token URL for AU region', function (): void {
    Http::fake([
        'https://au-api.halaxy.com/main/oauth/token' => Http::response([
            'access_token' => 'au-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $authenticator = createAuthenticator(region: Region::AU);
    $token = $authenticator->refreshToken();

    expect($token)->toBe('au-token');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://au-api.halaxy.com/main/oauth/token';
    });
});

test('uses correct token URL for EU region', function (): void {
    Http::fake([
        'https://eu-api.halaxy.com/main/oauth/token' => Http::response([
            'access_token' => 'eu-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $authenticator = createAuthenticator(region: Region::EU);
    $token = $authenticator->refreshToken();

    expect($token)->toBe('eu-token');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://eu-api.halaxy.com/main/oauth/token';
    });
});

test('uses separate cache keys for different regions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);

    $auAuthenticator = createAuthenticator(region: Region::AU, cache: $cache);
    $euAuthenticator = createAuthenticator(region: Region::EU, cache: $cache);

    $cache->put('halaxy.token.au', 'au-token', 3600);
    $cache->put('halaxy.token.eu', 'eu-token', 3600);

    expect($auAuthenticator->getAccessToken())->toBe('au-token')
        ->and($euAuthenticator->getAccessToken())->toBe('eu-token');
});

test('getAccessToken calls refreshToken when cache returns non-string', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'fresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);

    $cache = new CacheRepository(new ArrayStore);
    // Put a non-string value in cache
    $cache->put('halaxy.token.au', ['invalid' => 'data'], 3600);

    $authenticator = createAuthenticator(cache: $cache);
    $token = $authenticator->getAccessToken();

    expect($token)->toBe('fresh-token');

    Http::assertSentCount(1);
});
