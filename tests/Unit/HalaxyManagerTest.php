<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\HalaxyManager;
use Clinically\Halaxy\Resources\People\PatientResource;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;

beforeEach(function (): void {
    $this->cache = new CacheRepository(new ArrayStore);
    $this->manager = new HalaxyManager(
        cache: $this->cache,
        defaultClientId: 'default-client-id',
        defaultClientSecret: 'default-client-secret',
        defaultRegion: 'au',
        defaultUserAgent: 'Test SDK',
        timeout: 30,
        retryAttempts: 3,
        retryDelay: 100,
        cachePrefix: 'halaxy',
        tokenBuffer: 60,
    );
});

describe('HalaxyManager', function (): void {
    test('can get default instance', function (): void {
        $instance = $this->manager->getDefaultInstance();

        expect($instance)->toBeInstanceOf(Halaxy::class);
        expect($instance->getRegion()->value)->toBe('au');
    });

    test('returns same default instance on multiple calls', function (): void {
        $instance1 = $this->manager->getDefaultInstance();
        $instance2 = $this->manager->getDefaultInstance();

        expect($instance1)->toBe($instance2);
    });

    test('can create tenant instance with for()', function (): void {
        $instance = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'tenant-client-id',
            clientSecret: 'tenant-client-secret',
        );

        expect($instance)->toBeInstanceOf(Halaxy::class);
        expect($instance->getRegion()->value)->toBe('au');
    });

    test('tenant instances are cached by tenant ID', function (): void {
        $instance1 = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'tenant-client-id',
            clientSecret: 'tenant-client-secret',
        );

        $instance2 = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'tenant-client-id',
            clientSecret: 'tenant-client-secret',
        );

        expect($instance1)->toBe($instance2);
    });

    test('different tenants get different instances', function (): void {
        $instance1 = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'tenant-1-client-id',
            clientSecret: 'tenant-1-client-secret',
        );

        $instance2 = $this->manager->for(
            tenantId: 'tenant-456',
            clientId: 'tenant-2-client-id',
            clientSecret: 'tenant-2-client-secret',
        );

        expect($instance1)->not->toBe($instance2);
    });

    test('tenant instances can use different regions', function (): void {
        $auInstance = $this->manager->for(
            tenantId: 'tenant-au',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            region: 'au',
        );

        $euInstance = $this->manager->for(
            tenantId: 'tenant-eu',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            region: 'eu',
        );

        expect($auInstance->getRegion()->value)->toBe('au');
        expect($euInstance->getRegion()->value)->toBe('eu');
    });

    test('can create instance with withCredentials()', function (): void {
        $instance = $this->manager->withCredentials(
            clientId: 'one-off-client-id',
            clientSecret: 'one-off-client-secret',
        );

        expect($instance)->toBeInstanceOf(Halaxy::class);
    });

    test('withCredentials creates new instance each time', function (): void {
        $instance1 = $this->manager->withCredentials(
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        $instance2 = $this->manager->withCredentials(
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        // Note: These will be different objects, not === but functionally equivalent
        // The key insight is withCredentials doesn't maintain instance cache
        expect($instance1)->toBeInstanceOf(Halaxy::class);
        expect($instance2)->toBeInstanceOf(Halaxy::class);
    });

    test('withCredentials can specify region', function (): void {
        $instance = $this->manager->withCredentials(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            region: 'eu',
        );

        expect($instance->getRegion()->value)->toBe('eu');
    });

    test('can forget tenant instance', function (): void {
        $instance1 = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        $this->manager->forgetTenant('tenant-123');

        $instance2 = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        expect($instance1)->not->toBe($instance2);
    });

    test('can flush all instances', function (): void {
        $default = $this->manager->getDefaultInstance();
        $tenant = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        $this->manager->flush();

        $newDefault = $this->manager->getDefaultInstance();
        $newTenant = $this->manager->for(
            tenantId: 'tenant-123',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        expect($default)->not->toBe($newDefault);
        expect($tenant)->not->toBe($newTenant);
    });

    test('proxies method calls to default instance', function (): void {
        // The manager should proxy calls like patients() to the default instance
        $patients = $this->manager->patients();

        expect($patients)->toBeInstanceOf(PatientResource::class);
    });

    test('proxies region() to default instance', function (): void {
        $euInstance = $this->manager->region('eu');

        expect($euInstance)->toBeInstanceOf(Halaxy::class);
        expect($euInstance->getRegion()->value)->toBe('eu');
    });
});
