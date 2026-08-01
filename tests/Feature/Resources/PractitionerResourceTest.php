<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\People\PractitionerResource;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Fake the OAuth token request
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);
});

test('practitioners returns PractitionerResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->practitioners())->toBeInstanceOf(PractitionerResource::class);
});

test('can find a practitioner by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner/99001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()->find('99001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Practitioner')
        ->and($response->json()['id'])->toBe('99001')
        ->and($response->json()['name'][0]['family'])->toBe('Wilson')
        ->and($response->json()['name'][0]['given'])->toBe(['Sarah'])
        ->and($response->json()['name'][0]['prefix'])->toBe(['Dr.']);
});

test('can list practitioners', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list practitioners with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner?name=Wilson&_count=10' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Practitioner', 'id' => '99001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()->list(['name' => 'Wilson', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can create a practitioner', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner' => Http::response([
            'resourceType' => 'Practitioner',
            'id' => '99999',
            'name' => [['family' => 'New', 'given' => ['Doctor']]],
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()->create([
        'name' => [['family' => 'New', 'given' => ['Doctor'], 'prefix' => ['Dr.']]],
        'gender' => 'male',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('99999');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Practitioner')
            && ($body['resourceType'] ?? null) === 'Practitioner'
            && ($body['gender'] ?? null) === 'male';
    });
});

test('create auto-adds resourceType if missing', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner' => Http::response([
            'resourceType' => 'Practitioner',
            'id' => '99999',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->practitioners()->create([
        'name' => [['family' => 'Test']],
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['resourceType']) && $body['resourceType'] === 'Practitioner';
    });
});

test('can use query builder to search practitioners', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Practitioner', 'id' => '99001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()
        ->query()
        ->where('family', 'Wilson')
        ->where('active', 'true')
        ->orderBy('name')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'family=Wilson')
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
        '*/Practitioner/99001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()->find('99001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Practitioner');
});

test('bundle response provides pagination info', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Practitioner' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitioners()->list();

    expect($response->hasNextPage())->toBeTrue()
        ->and($response->nextPageUrl())->toBe('https://au-api.halaxy.com/main/Practitioner?_count=50&page=2')
        ->and($response->links())->toHaveKey('self')
        ->and($response->links())->toHaveKey('next');
});

test('practitioner fixture has valid FHIR structure', function (): void {
    $practitioner = json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner.json'), true);

    expect($practitioner['resourceType'])->toBe('Practitioner')
        ->and($practitioner['id'])->toBe('99001')
        ->and($practitioner['active'])->toBeTrue()
        ->and($practitioner['gender'])->toBe('female')
        ->and($practitioner['identifier'])->toBeArray()
        ->and($practitioner['identifier'][0]['system'])->toContain('halaxy.com')
        ->and($practitioner['name'])->toBeArray()
        ->and($practitioner['telecom'])->toBeArray()
        ->and($practitioner['qualification'])->toBeArray();
});

test('practitioner has HPII identifier', function (): void {
    $practitioner = json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner.json'), true);

    $hpii = collect($practitioner['identifier'])->first(function ($identifier) {
        return str_contains($identifier['system'] ?? '', 'hpii');
    });

    expect($hpii)->not->toBeNull()
        ->and($hpii['value'])->toBe('8003614900012345');
});
