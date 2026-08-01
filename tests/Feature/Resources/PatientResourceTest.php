<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\People\PatientResource;
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

test('patients returns PatientResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->patients())->toBeInstanceOf(PatientResource::class);
});

test('can find a patient by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/12345' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/patient.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->find('12345');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Patient')
        ->and($response->json()['id'])->toBe('12345')
        ->and($response->json()['name'][0]['family'])->toBe('Smith')
        ->and($response->json()['name'][0]['given'])->toBe(['John', 'James']);
});

test('can list patients', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/patient-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list patients with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient?name=John&_count=10' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Patient', 'id' => '12345']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->list(['name' => 'John', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can create a patient', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response([
            'resourceType' => 'Patient',
            'id' => '99999',
            'name' => [['family' => 'New', 'given' => ['Test']]],
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->create([
        'name' => [['family' => 'New', 'given' => ['Test']]],
        'gender' => 'male',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('99999');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Patient')
            && ($body['resourceType'] ?? null) === 'Patient'
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
        '*/Patient' => Http::response([
            'resourceType' => 'Patient',
            'id' => '99999',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->patients()->create([
        'name' => [['family' => 'Test']],
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['resourceType']) && $body['resourceType'] === 'Patient';
    });
});

test('can update a patient', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/12345' => Http::response([
            'resourceType' => 'Patient',
            'id' => '12345',
            'name' => [['family' => 'Updated']],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->update('12345', [
        'name' => [['family' => 'Updated']],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->json()['name'][0]['family'])->toBe('Updated');

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'Patient/12345');
    });
});

test('can replace a patient', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/12345' => Http::response([
            'resourceType' => 'Patient',
            'id' => '12345',
            'name' => [['family' => 'Replaced']],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->replace('12345', [
        'name' => [['family' => 'Replaced']],
    ]);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'PUT'
            && str_contains($request->url(), 'Patient/12345')
            && ($body['resourceType'] ?? null) === 'Patient'
            && ($body['id'] ?? null) === '12345';
    });
});

test('can use query builder to search patients', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Patient', 'id' => '12345']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()
        ->query()
        ->where('family', 'Smith')
        ->where('birthdate', 'gt', '1980-01-01')
        ->orderBy('name')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'family=Smith')
            && str_contains($url, 'birthdate=gt1980-01-01')
            && str_contains($url, '_sort=name')
            && str_contains($url, '_count=25');
    });
});

test('can export patient ids', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/$export-ids' => Http::response([
            'resourceType' => 'Parameters',
            'parameter' => [
                ['name' => 'patient', 'valueReference' => ['reference' => 'Patient/12345']],
                ['name' => 'patient', 'valueReference' => ['reference' => 'Patient/67890']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->exportIds();

    expect($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Parameters');
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient/12345' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/patient.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->find('12345');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Patient');
});

test('bundle response provides pagination info', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Patient' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/patient-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->patients()->list();

    expect($response->hasNextPage())->toBeTrue()
        ->and($response->nextPageUrl())->toBe('https://au-api.halaxy.com/main/Patient?_count=50&page=2')
        ->and($response->links())->toHaveKey('self')
        ->and($response->links())->toHaveKey('next');
});
