<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Tests;

use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\HalaxyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            HalaxyServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Halaxy' => Halaxy::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('halaxy.client_id', 'test-client-id');
        $app['config']->set('halaxy.client_secret', 'test-client-secret');
        $app['config']->set('halaxy.region', 'au');
        $app['config']->set('halaxy.cache.enabled', false);
        $app['config']->set('halaxy.webhooks.enabled', true);
        $app['config']->set('halaxy.webhooks.signing_secret', 'test-webhook-secret');
    }

    /**
     * Get the path to a test fixture file.
     */
    protected function fixturePath(string $filename): string
    {
        return __DIR__.'/Fixtures/'.$filename;
    }

    /**
     * Get the contents of a test fixture file.
     */
    protected function fixture(string $filename): string
    {
        return file_get_contents($this->fixturePath($filename));
    }

    /**
     * Get the contents of a test fixture file as an array.
     *
     * @return array<string, mixed>
     */
    protected function fixtureArray(string $filename): array
    {
        return json_decode($this->fixture($filename), true);
    }

    /**
     * Create the spatie webhook_calls table for pipeline tests.
     */
    protected function migrateWebhookCallsTable(): void
    {
        foreach ([
            'create_webhook_calls_table',
            'add_attachments_to_webhook_calls_table',
        ] as $stub) {
            $migration = include dirname(__DIR__)."/vendor/spatie/laravel-webhook-client/database/migrations/{$stub}.php.stub";
            $migration->up();
        }
    }
}
