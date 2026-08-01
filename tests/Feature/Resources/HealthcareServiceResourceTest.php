<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Scheduling\HealthcareServiceResource;
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

test('healthcareServices returns HealthcareServiceResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->healthcareServices())->toBeInstanceOf(HealthcareServiceResource::class);
});

test('can find a healthcare service by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/HealthcareService/HS-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/healthcare-service.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->healthcareServices()->find('HS-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('HealthcareService')
        ->and($response->json()['id'])->toBe('HS-001')
        ->and($response->json()['active'])->toBeTrue()
        ->and($response->json()['name'])->toBe('General Practice Consultation');
});

test('can list healthcare services', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/HealthcareService' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'HealthcareService', 'id' => 'HS-001']],
                ['resource' => ['resourceType' => 'HealthcareService', 'id' => 'HS-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->healthcareServices()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can list healthcare services with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/HealthcareService?*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'HealthcareService', 'id' => 'HS-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->healthcareServices()->list(['organization' => 'Organization/88001']);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can use query builder to search healthcare services', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/HealthcareService*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'HealthcareService', 'id' => 'HS-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->healthcareServices()
        ->query()
        ->where('active', 'true')
        ->where('organization', 'Organization/88001')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/HealthcareService/HS-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/healthcare-service.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->healthcareServices()->find('HS-001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('HealthcareService');
});

test('healthcare service fixture has valid FHIR structure', function (): void {
    $service = json_decode(file_get_contents(__DIR__.'/../../Fixtures/healthcare-service.json'), true);

    expect($service['resourceType'])->toBe('HealthcareService')
        ->and($service['id'])->toBe('HS-001')
        ->and($service['active'])->toBeTrue()
        ->and($service['name'])->toBe('General Practice Consultation')
        ->and($service['providedBy'])->toBeArray()
        ->and($service['category'])->toBeArray()
        ->and($service['type'])->toBeArray()
        ->and($service['appointmentRequired'])->toBeTrue();
});

test('healthcare service has organization reference', function (): void {
    $service = json_decode(file_get_contents(__DIR__.'/../../Fixtures/healthcare-service.json'), true);

    expect($service['providedBy']['reference'])->toBe('Organization/88001')
        ->and($service['providedBy']['display'])->toBe('Sydney Medical Centre');
});
