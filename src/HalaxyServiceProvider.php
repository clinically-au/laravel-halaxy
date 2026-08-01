<?php

declare(strict_types=1);

namespace Clinically\Halaxy;

use Clinically\Halaxy\Contracts\AuthenticatorInterface;
use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Http\Authenticator;
use Clinically\Halaxy\Http\Client;
use Clinically\Halaxy\Webhooks\HalaxySignatureValidator;
use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;
use Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookClientServiceProvider;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

final class HalaxyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/halaxy.php', 'halaxy');

        $this->registerManager();
        $this->registerAuthenticator();
        $this->registerClient();
        $this->registerHalaxy();
        $this->registerWebhookConfig();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/halaxy.php' => config_path('halaxy.php'),
            ], 'halaxy-config');
        }

        $this->registerWebhookRoutes();
    }

    /**
     * Register the HalaxyManager (factory for multi-tenant support).
     */
    private function registerManager(): void
    {
        $this->app->singleton(HalaxyManager::class, function ($app): HalaxyManager {
            $config = $app['config']['halaxy'];

            return new HalaxyManager(
                cache: $app->make(CacheRepository::class),
                defaultClientId: $config['client_id'] ?? '',
                defaultClientSecret: $config['client_secret'] ?? '',
                defaultRegion: $config['region'] ?? 'au',
                defaultUserAgent: $config['user_agent'] ?? 'Clinically Halaxy SDK',
                timeout: $config['http']['timeout'] ?? 30,
                retryAttempts: $config['http']['retry_attempts'] ?? 3,
                retryDelay: $config['http']['retry_delay'] ?? 100,
                cachePrefix: $config['cache']['prefix'] ?? 'halaxy',
                tokenBuffer: $config['cache']['token_buffer'] ?? 60,
            );
        });

        $this->app->alias(HalaxyManager::class, 'halaxy');
    }

    /**
     * Register the authenticator (for default config-based credentials).
     */
    private function registerAuthenticator(): void
    {
        $this->app->singleton(AuthenticatorInterface::class, function ($app): Authenticator {
            $config = $app['config']['halaxy'];

            return new Authenticator(
                clientId: $config['client_id'] ?? '',
                clientSecret: $config['client_secret'] ?? '',
                region: Region::fromString($config['region'] ?? 'au'),
                cache: $app->make(CacheRepository::class),
                cachePrefix: $config['cache']['prefix'] ?? 'halaxy',
                tokenBuffer: $config['cache']['token_buffer'] ?? 60,
                userAgent: $config['user_agent'] ?? 'Clinically Halaxy SDK',
            );
        });
    }

    /**
     * Register the HTTP client (for default config-based credentials).
     */
    private function registerClient(): void
    {
        $this->app->singleton(ClientInterface::class, function ($app): Client {
            $config = $app['config']['halaxy'];

            return new Client(
                authenticator: $app->make(AuthenticatorInterface::class),
                region: Region::fromString($config['region'] ?? 'au'),
                userAgent: $config['user_agent'] ?? 'Clinically Halaxy SDK',
                timeout: $config['http']['timeout'] ?? 30,
                retryAttempts: $config['http']['retry_attempts'] ?? 3,
                retryDelay: $config['http']['retry_delay'] ?? 100,
            );
        });
    }

    /**
     * Register the main Halaxy service (default instance for backwards compatibility).
     */
    private function registerHalaxy(): void
    {
        $this->app->singleton(Halaxy::class, function ($app): Halaxy {
            // Use the manager's default instance for backwards compatibility
            return $app->make(HalaxyManager::class)->getDefaultInstance();
        });
    }

    /**
     * Merge one webhook-client config per Halaxy endpoint.
     */
    private function registerWebhookConfig(): void
    {
        // Route::webhooks() is defined by Spatie's provider during package
        // registration. Load the provider unconditionally, then gate Halaxy's
        // configs and routes at boot so late host config overrides are honored.
        if (method_exists($this->app, 'providerIsLoaded') && ! $this->app->providerIsLoaded(WebhookClientServiceProvider::class)) {
            $this->app->register(WebhookClientServiceProvider::class);
        }

        $this->app->booted(function (): void {
            // Read the enablement flag at boot so config overrides applied by
            // a host app or test harness after register() are still honored.
            if (! config('halaxy.webhooks.enabled', false)) {
                return;
            }

            $webhooks = config('halaxy.webhooks');

            $halaxyConfigs = array_map(static fn (string $name): array => [
                'name' => $name,
                'signing_secret' => $webhooks['signing_secret'] ?? null,
                'signature_header_name' => $webhooks['signature_header_name'] ?? 'Authorization',
                'signature_validator' => $webhooks['signature_validator'] ?? HalaxySignatureValidator::class,
                'webhook_profile' => $webhooks['webhook_profile'] ?? ProcessEverythingWebhookProfile::class,
                'webhook_response' => DefaultRespondsTo::class,
                'webhook_model' => $webhooks['webhook_model'] ?? WebhookCall::class,
                'store_headers' => $webhooks['store_headers'] ?? [],
                'process_webhook_job' => $webhooks['process_webhook_job'] ?? ProcessHalaxyWebhookJob::class,
            ], HalaxyWebhookRoutes::configNames());

            $existingConfigs = array_filter(
                config('webhook-client.configs', []),
                // Drop spatie's unpublished placeholder entry: with no published
                // webhook-client.php the package config ships name "default" with an
                // empty job class, and spatie's config repository would throw
                // InvalidConfig for it on the first webhook request.
                static fn (array $config): bool => ! (
                    ($config['name'] ?? '') === 'default'
                    && ($config['process_webhook_job'] ?? '') === ''
                ),
            );

            config(['webhook-client.configs' => [
                ...array_values($existingConfigs),
                ...$halaxyConfigs,
            ]]);
        });
    }

    /**
     * Register webhook routes (unless the host registers them itself).
     */
    private function registerWebhookRoutes(): void
    {
        if (! config('halaxy.webhooks.enabled', false)) {
            return;
        }

        if (! config('halaxy.webhooks.register_routes', true)) {
            return;
        }

        HalaxyWebhookRoutes::register();
    }
}
