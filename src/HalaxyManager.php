<?php

declare(strict_types=1);

namespace Clinically\Halaxy;

use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Http\Authenticator;
use Clinically\Halaxy\Http\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Factory class for creating Halaxy instances.
 *
 * Supports both single-tenant (config-based) and multi-tenant usage patterns.
 *
 * Single-tenant (using config/env):
 *   Halaxy::patients()->find($id);
 *
 * Multi-tenant (explicit credentials):
 *   $halaxy = Halaxy::for('tenant-123', $clientId, $clientSecret);
 *   $halaxy->patients()->find($id);
 *
 *   // Or use withCredentials for one-off usage:
 *   Halaxy::withCredentials($clientId, $clientSecret)->patients()->find($id);
 */
final class HalaxyManager
{
    /**
     * Cached Halaxy instances keyed by tenant identifier.
     *
     * @var array<string, Halaxy>
     */
    private array $instances = [];

    /**
     * The default Halaxy instance (config-based).
     */
    private ?Halaxy $defaultInstance = null;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $defaultClientId,
        private readonly string $defaultClientSecret,
        private readonly string $defaultRegion,
        private readonly string $defaultUserAgent,
        private readonly int $timeout,
        private readonly int $retryAttempts,
        private readonly int $retryDelay,
        private readonly string $cachePrefix,
        private readonly int $tokenBuffer,
    ) {}

    /**
     * Get the default Halaxy instance (uses config credentials).
     */
    public function getDefaultInstance(): Halaxy
    {
        if ($this->defaultInstance === null) {
            $this->defaultInstance = $this->createInstance(
                clientId: $this->defaultClientId,
                clientSecret: $this->defaultClientSecret,
                region: $this->defaultRegion,
                cacheKey: 'default',
            );
        }

        return $this->defaultInstance;
    }

    /**
     * Create a Halaxy instance for a specific tenant.
     *
     * Instances are cached by tenant ID for the lifetime of the request/manager.
     * Each tenant gets isolated authentication (separate OAuth tokens).
     *
     * @param  string  $tenantId  Unique identifier for the tenant (used for token caching)
     * @param  string  $clientId  OAuth client ID for this tenant
     * @param  string  $clientSecret  OAuth client secret for this tenant
     * @param  string|null  $region  API region (defaults to config value)
     */
    public function for(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        ?string $region = null,
    ): Halaxy {
        $cacheKey = "tenant.{$tenantId}";

        if (! isset($this->instances[$cacheKey])) {
            $this->instances[$cacheKey] = $this->createInstance(
                clientId: $clientId,
                clientSecret: $clientSecret,
                region: $region ?? $this->defaultRegion,
                cacheKey: $cacheKey,
            );
        }

        return $this->instances[$cacheKey];
    }

    /**
     * Create a Halaxy instance with specific credentials (not cached).
     *
     * Use this for one-off requests where you don't need instance caching.
     * Each call creates a new instance with its own authenticator.
     *
     * @param  string  $clientId  OAuth client ID
     * @param  string  $clientSecret  OAuth client secret
     * @param  string|null  $region  API region (defaults to config value)
     */
    public function withCredentials(
        string $clientId,
        string $clientSecret,
        ?string $region = null,
    ): Halaxy {
        // Generate a unique cache key based on credentials hash
        $credentialsHash = hash('xxh128', $clientId.$clientSecret);
        $cacheKey = "credentials.{$credentialsHash}";

        return $this->createInstance(
            clientId: $clientId,
            clientSecret: $clientSecret,
            region: $region ?? $this->defaultRegion,
            cacheKey: $cacheKey,
        );
    }

    /**
     * Clear cached instance for a specific tenant.
     */
    public function forgetTenant(string $tenantId): void
    {
        $cacheKey = "tenant.{$tenantId}";

        if (isset($this->instances[$cacheKey])) {
            unset($this->instances[$cacheKey]);
        }

        // Also clear the cached OAuth token
        $this->cache->forget("{$this->cachePrefix}.token.{$cacheKey}");
    }

    /**
     * Clear all cached instances and reset the manager.
     */
    public function flush(): void
    {
        $this->instances = [];
        $this->defaultInstance = null;
    }

    /**
     * Create a new Halaxy instance with the given configuration.
     */
    private function createInstance(
        string $clientId,
        string $clientSecret,
        string $region,
        string $cacheKey,
    ): Halaxy {
        $regionEnum = Region::fromString($region);

        $authenticator = new Authenticator(
            clientId: $clientId,
            clientSecret: $clientSecret,
            region: $regionEnum,
            cache: $this->cache,
            cachePrefix: "{$this->cachePrefix}.{$cacheKey}",
            tokenBuffer: $this->tokenBuffer,
            userAgent: $this->defaultUserAgent,
        );

        $client = new Client(
            authenticator: $authenticator,
            region: $regionEnum,
            userAgent: $this->defaultUserAgent,
            timeout: $this->timeout,
            retryAttempts: $this->retryAttempts,
            retryDelay: $this->retryDelay,
        );

        return new Halaxy(
            client: $client,
            region: $regionEnum,
        );
    }

    /**
     * Proxy method calls to the default instance.
     *
     * This allows the manager to be used directly like:
     *   $manager->patients()->find($id)
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->getDefaultInstance()->{$method}(...$arguments);
    }
}
