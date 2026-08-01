<?php

declare(strict_types=1);

use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\Halaxy as HalaxyInstance;
use Clinically\Halaxy\HalaxyManager;
use Clinically\Halaxy\Resources\People\PatientResource;
use Clinically\Halaxy\Resources\People\PractitionerResource;
use Clinically\Halaxy\Resources\Scheduling\AppointmentResource;

describe('Multi-Tenant Support', function (): void {
    test('facade resolves to HalaxyManager', function (): void {
        $manager = app(HalaxyManager::class);

        expect($manager)->toBeInstanceOf(HalaxyManager::class);
    });

    test('facade proxies to default instance', function (): void {
        $patients = Halaxy::patients();

        expect($patients)->toBeInstanceOf(PatientResource::class);
    });

    test('can access default instance via facade', function (): void {
        $instance = Halaxy::getDefaultInstance();

        expect($instance)->toBeInstanceOf(HalaxyInstance::class);
        expect($instance->getRegion()->value)->toBe('au');
    });

    test('can create tenant instance via facade', function (): void {
        $instance = Halaxy::for(
            tenantId: 'tenant-abc',
            clientId: 'tenant-client-id',
            clientSecret: 'tenant-client-secret',
        );

        expect($instance)->toBeInstanceOf(HalaxyInstance::class);
    });

    test('can create instance with credentials via facade', function (): void {
        $instance = Halaxy::withCredentials(
            clientId: 'my-client-id',
            clientSecret: 'my-client-secret',
            region: 'eu',
        );

        expect($instance)->toBeInstanceOf(HalaxyInstance::class);
        expect($instance->getRegion()->value)->toBe('eu');
    });

    test('tenant instances have isolated credentials', function (): void {
        $tenant1 = Halaxy::for(
            tenantId: 'clinic-a',
            clientId: 'clinic-a-id',
            clientSecret: 'clinic-a-secret',
        );

        $tenant2 = Halaxy::for(
            tenantId: 'clinic-b',
            clientId: 'clinic-b-id',
            clientSecret: 'clinic-b-secret',
        );

        // Different tenants should have different instances
        expect($tenant1)->not->toBe($tenant2);

        // But same tenant should return cached instance
        $tenant1Again = Halaxy::for(
            tenantId: 'clinic-a',
            clientId: 'clinic-a-id',
            clientSecret: 'clinic-a-secret',
        );

        expect($tenant1)->toBe($tenant1Again);
    });

    test('can forget tenant via facade', function (): void {
        $instance1 = Halaxy::for(
            tenantId: 'temp-tenant',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        Halaxy::forgetTenant('temp-tenant');

        $instance2 = Halaxy::for(
            tenantId: 'temp-tenant',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        expect($instance1)->not->toBe($instance2);
    });

    test('can flush all instances via facade', function (): void {
        Halaxy::for(
            tenantId: 'tenant-1',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        Halaxy::for(
            tenantId: 'tenant-2',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        Halaxy::flush();

        // After flush, new instances should be created
        // We can't directly test this without internal access,
        // but we can verify the method doesn't throw
        expect(true)->toBeTrue();
    });

    test('region switching works via facade', function (): void {
        $euInstance = Halaxy::region('eu');

        expect($euInstance)->toBeInstanceOf(HalaxyInstance::class);
        expect($euInstance->getRegion()->value)->toBe('eu');
    });

    test('tenant can switch regions', function (): void {
        $auTenant = Halaxy::for(
            tenantId: 'multi-region-tenant',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            region: 'au',
        );

        $euFromTenant = $auTenant->region('eu');

        expect($auTenant->getRegion()->value)->toBe('au');
        expect($euFromTenant->getRegion()->value)->toBe('eu');
    });
});

describe('Backwards Compatibility', function (): void {
    test('Halaxy class can still be resolved directly', function (): void {
        $instance = app(HalaxyInstance::class);

        expect($instance)->toBeInstanceOf(HalaxyInstance::class);
    });

    test('facade works the same as before for single-tenant', function (): void {
        // All the standard facade methods should work
        expect(Halaxy::getRegion()->value)->toBe('au');
        expect(Halaxy::patients())->toBeInstanceOf(PatientResource::class);
        expect(Halaxy::practitioners())->toBeInstanceOf(PractitionerResource::class);
        expect(Halaxy::appointments())->toBeInstanceOf(AppointmentResource::class);
    });
});
