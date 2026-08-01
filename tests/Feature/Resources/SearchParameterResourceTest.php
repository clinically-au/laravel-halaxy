<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Foundations\SearchParameterResource;
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

test('searchParameters returns SearchParameterResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->searchParameters())->toBeInstanceOf(SearchParameterResource::class);
});

test('can list search parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/SearchParameter' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 3,
            'entry' => [
                ['resource' => json_decode(file_get_contents(__DIR__.'/../../Fixtures/search-parameter.json'), true)],
                ['resource' => ['resourceType' => 'SearchParameter', 'id' => 'patient-birthdate', 'name' => 'birthdate']],
                ['resource' => ['resourceType' => 'SearchParameter', 'id' => 'patient-identifier', 'name' => 'identifier']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->searchParameters()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(3);
});

test('can list search parameters with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/SearchParameter?*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'SearchParameter', 'id' => 'patient-name', 'name' => 'name']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->searchParameters()->list(['base' => 'Patient']);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can use query builder to search parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/SearchParameter*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'SearchParameter', 'id' => 'patient-name', 'name' => 'name']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->searchParameters()
        ->query()
        ->where('base', 'Patient')
        ->where('type', 'string')
        ->limit(50)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('search parameter fixture has valid FHIR structure', function (): void {
    $sp = json_decode(file_get_contents(__DIR__.'/../../Fixtures/search-parameter.json'), true);

    expect($sp['resourceType'])->toBe('SearchParameter')
        ->and($sp['id'])->toBe('patient-name')
        ->and($sp['name'])->toBe('name')
        ->and($sp['status'])->toBe('active')
        ->and($sp['code'])->toBe('name')
        ->and($sp['base'])->toContain('Patient')
        ->and($sp['type'])->toBe('string');
});
