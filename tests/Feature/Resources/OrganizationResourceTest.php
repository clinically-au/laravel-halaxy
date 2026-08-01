<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\People\OrganizationResource;
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

test('organizations returns OrganizationResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->organizations())->toBeInstanceOf(OrganizationResource::class);
});

test('can find an organization by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization/88001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/organization.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->organizations()->find('88001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Organization')
        ->and($response->json()['id'])->toBe('88001')
        ->and($response->json()['name'])->toBe('Sydney Medical Centre')
        ->and($response->json()['active'])->toBeTrue();
});

test('can list organizations', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/organization-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->organizations()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list organizations with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization?name=Sydney&_count=10' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Organization', 'id' => '88001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->organizations()->list(['name' => 'Sydney', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can create an organization', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization' => Http::response([
            'resourceType' => 'Organization',
            'id' => '88003',
            'name' => 'New Clinic',
            'active' => true,
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->organizations()->create([
        'name' => 'New Clinic',
        'active' => true,
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('88003');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Organization')
            && ($body['resourceType'] ?? null) === 'Organization'
            && ($body['name'] ?? null) === 'New Clinic';
    });
});

test('create auto-adds resourceType if missing', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization' => Http::response([
            'resourceType' => 'Organization',
            'id' => '88003',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->organizations()->create([
        'name' => 'Test Clinic',
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['resourceType']) && $body['resourceType'] === 'Organization';
    });
});

test('can use query builder to search organizations', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Organization', 'id' => '88001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->organizations()
        ->query()
        ->where('name', 'contains', 'Medical')
        ->where('active', 'true')
        ->where('address-state', 'NSW')
        ->orderBy('name')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'name:contains=Medical')
            && str_contains($url, 'active=true')
            && str_contains($url, '_sort=name')
            && str_contains($url, '_count=25');
    });
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Organization/88001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/organization.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->organizations()->find('88001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Organization');
});

test('organization fixture has valid FHIR structure', function (): void {
    $organization = json_decode(file_get_contents(__DIR__.'/../../Fixtures/organization.json'), true);

    expect($organization['resourceType'])->toBe('Organization')
        ->and($organization['id'])->toBe('88001')
        ->and($organization['active'])->toBeTrue()
        ->and($organization['name'])->toBe('Sydney Medical Centre')
        ->and($organization['identifier'])->toBeArray()
        ->and($organization['type'])->toBeArray()
        ->and($organization['telecom'])->toBeArray()
        ->and($organization['address'])->toBeArray();
});

test('organization has HPIO identifier', function (): void {
    $organization = json_decode(file_get_contents(__DIR__.'/../../Fixtures/organization.json'), true);

    $hpio = collect($organization['identifier'])->first(function ($identifier) {
        return str_contains($identifier['system'] ?? '', 'hpio');
    });

    expect($hpio)->not->toBeNull()
        ->and($hpio['value'])->toBe('8003629900012345');
});

test('organization has address details', function (): void {
    $organization = json_decode(file_get_contents(__DIR__.'/../../Fixtures/organization.json'), true);

    $address = $organization['address'][0];

    expect($address['city'])->toBe('Sydney')
        ->and($address['state'])->toBe('NSW')
        ->and($address['postalCode'])->toBe('2000')
        ->and($address['country'])->toBe('AU');
});
