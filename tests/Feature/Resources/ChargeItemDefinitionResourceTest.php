<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\ChargeItemDefinitionResource;
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

test('chargeItemDefinitions returns ChargeItemDefinitionResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->chargeItemDefinitions())->toBeInstanceOf(ChargeItemDefinitionResource::class);
});

test('can find a charge item definition by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/ChargeItemDefinition/CID-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/charge-item-definition.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->chargeItemDefinitions()->find('CID-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('ChargeItemDefinition')
        ->and($response->json()['id'])->toBe('CID-001')
        ->and($response->json()['status'])->toBe('active');
});

test('can list charge item definitions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/ChargeItemDefinition' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'ChargeItemDefinition', 'id' => 'CID-001']],
                ['resource' => ['resourceType' => 'ChargeItemDefinition', 'id' => 'CID-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->chargeItemDefinitions()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can use query builder to search charge item definitions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/ChargeItemDefinition*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'ChargeItemDefinition', 'id' => 'CID-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->chargeItemDefinitions()
        ->query()
        ->where('status', 'active')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('charge item definition fixture has valid FHIR structure', function (): void {
    $cid = json_decode(file_get_contents(__DIR__.'/../../Fixtures/charge-item-definition.json'), true);

    expect($cid['resourceType'])->toBe('ChargeItemDefinition')
        ->and($cid['id'])->toBe('CID-001')
        ->and($cid['status'])->toBe('active')
        ->and($cid['title'])->toBe('Standard GP Consultation')
        ->and($cid['code'])->toBeArray()
        ->and($cid['propertyGroup'])->toBeArray();
});
