<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Foundations\CapabilityStatementResource;
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

test('capabilities returns CapabilityStatementResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->capabilities())->toBeInstanceOf(CapabilityStatementResource::class);
});

test('can get capability statement', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/metadata' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/capability-statement.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->capabilities()->get();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('CapabilityStatement')
        ->and($response->json()['status'])->toBe('active')
        ->and($response->json()['fhirVersion'])->toBe('4.3.0');
});

test('capability statement response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/metadata' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/capability-statement.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->capabilities()->get();

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('CapabilityStatement');
});

test('capability statement fixture has valid FHIR structure', function (): void {
    $cs = json_decode(file_get_contents(__DIR__.'/../../Fixtures/capability-statement.json'), true);

    expect($cs['resourceType'])->toBe('CapabilityStatement')
        ->and($cs['status'])->toBe('active')
        ->and($cs['kind'])->toBe('instance')
        ->and($cs['fhirVersion'])->toBe('4.3.0')
        ->and($cs['format'])->toContain('application/fhir+json')
        ->and($cs['rest'])->toBeArray()
        ->and($cs['rest'][0]['mode'])->toBe('server');
});

test('capability statement lists supported resources', function (): void {
    $cs = json_decode(file_get_contents(__DIR__.'/../../Fixtures/capability-statement.json'), true);

    $resources = collect($cs['rest'][0]['resource'])->pluck('type')->toArray();

    expect($resources)->toContain('Patient')
        ->and($resources)->toContain('Practitioner')
        ->and($resources)->toContain('Appointment');
});
