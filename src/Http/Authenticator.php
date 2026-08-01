<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Http;

use Clinically\Halaxy\Contracts\AuthenticatorInterface;
use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Exceptions\AuthenticationException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;

final class Authenticator implements AuthenticatorInterface
{
    private const TOKEN_ENDPOINT = 'oauth/token';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly Region $region,
        private readonly CacheRepository $cache,
        private readonly string $cachePrefix = 'halaxy',
        private readonly int $tokenBuffer = 60,
        private readonly string $userAgent = 'Clinically Halaxy SDK',
    ) {}

    /**
     * Get a valid access token, refreshing if necessary.
     */
    public function getAccessToken(): string
    {
        $cacheKey = $this->getCacheKey();
        $token = $this->cache->get($cacheKey);

        if ($token !== null && is_string($token)) {
            return $token;
        }

        return $this->refreshToken();
    }

    /**
     * Force refresh of the access token.
     */
    public function refreshToken(): string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw AuthenticationException::missingCredentials();
        }

        // Halaxy's gateway rejects requests without a User-Agent (403).
        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->post($this->getTokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];

            throw AuthenticationException::invalidCredentials(
                trim(sprintf(
                    'HTTP %d %s %s',
                    $response->status(),
                    $body['error'] ?? '',
                    $body['error_description'] ?? '',
                )),
            );
        }

        $data = $response->json();

        if (! isset($data['access_token'])) {
            throw AuthenticationException::invalidCredentials();
        }

        $token = $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 900); // Default 15 minutes

        // Cache the token with a buffer before actual expiry
        $ttl = max($expiresIn - $this->tokenBuffer, 60);
        $this->cache->put($this->getCacheKey(), $token, $ttl);

        return $token;
    }

    /**
     * Clear any cached tokens.
     */
    public function clearToken(): void
    {
        $this->cache->forget($this->getCacheKey());
    }

    /**
     * Get the token endpoint URL.
     */
    private function getTokenUrl(): string
    {
        return $this->region->baseUrl().self::TOKEN_ENDPOINT;
    }

    /**
     * Get the cache key for the token.
     */
    private function getCacheKey(): string
    {
        return "{$this->cachePrefix}.token.{$this->region->value}";
    }
}
