<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Tests;

use Dotenv\Dotenv;

/**
 * Base class for tests that hit the real Halaxy API.
 *
 * Credentials are read from the package's .env file (see .env.example) and
 * point at a live production practice. Tests extending this class must only
 * read the curated test patients listed in HALAXY_TEST_PATIENT_IDS. Writes
 * are allowed only when HALAXY_ALLOW_WRITES=true, and any record a test
 * creates must be unmistakably marked as SDK test data — Halaxy has no
 * Patient delete API, so created records persist in the practice.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (static::credentials() === null) {
            $this->markTestSkipped('Halaxy integration credentials not configured in .env');
        }
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if (($credentials = static::credentials()) === null) {
            return;
        }

        $app['config']->set('halaxy.client_id', $credentials['client_id']);
        $app['config']->set('halaxy.client_secret', $credentials['client_secret']);
        $app['config']->set('halaxy.region', $credentials['region']);
    }

    /**
     * @return array{client_id: string, client_secret: string, region: string}|null
     */
    protected static function credentials(): ?array
    {
        Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

        $clientId = $_ENV['HALAXY_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['HALAXY_CLIENT_SECRET'] ?? '';

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'region' => $_ENV['HALAXY_REGION'] ?? 'au',
        ];
    }

    /**
     * Whether integration tests may create/update records in the practice.
     */
    public static function writesAllowed(): bool
    {
        return filter_var($_ENV['HALAXY_ALLOW_WRITES'] ?? false, FILTER_VALIDATE_BOOL);
    }

    protected function skipUnlessWritesAllowed(): void
    {
        if (! static::writesAllowed()) {
            $this->markTestSkipped('HALAXY_ALLOW_WRITES is not enabled in .env');
        }
    }

    /**
     * IDs of the curated test patients integration tests may read.
     *
     * @return array<int, string>
     */
    public static function curatedPatientIds(): array
    {
        $ids = $_ENV['HALAXY_TEST_PATIENT_IDS'] ?? '';

        return array_values(array_filter(array_map('trim', explode(',', $ids))));
    }
}
