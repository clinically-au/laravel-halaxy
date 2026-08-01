<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\CoverageResource;
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

test('coverages returns CoverageResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->coverages())->toBeInstanceOf(CoverageResource::class);
});

test('can find a coverage by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Coverage/66001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/coverage.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()->find('66001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Coverage')
        ->and($response->json()['id'])->toBe('66001')
        ->and($response->json()['status'])->toBe('active');
});

test('can list coverages', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Coverage' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/coverage-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list coverages with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Coverage?*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Coverage', 'id' => '66001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()->list(['beneficiary' => 'Patient/12345', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can create a coverage', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Coverage' => Http::response([
            'resourceType' => 'Coverage',
            'id' => '66003',
            'status' => 'active',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()->create([
        'status' => 'active',
        'beneficiary' => ['reference' => 'Patient/12345'],
        'payor' => [['reference' => 'Organization/88001']],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('66003');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Coverage')
            && ($body['resourceType'] ?? null) === 'Coverage';
    });
});

test('can update a coverage', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Coverage/66001' => Http::response([
            'resourceType' => 'Coverage',
            'id' => '66001',
            'status' => 'cancelled',
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()->update('66001', [
        'status' => 'cancelled',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->json()['status'])->toBe('cancelled');

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'Coverage/66001');
    });
});

test('can use query builder to search coverages', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Coverage*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Coverage', 'id' => '66001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()
        ->query()
        ->where('beneficiary', 'Patient/12345')
        ->where('status', 'active')
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
        '*/Coverage/66001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/coverage.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->coverages()->find('66001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Coverage');
});

test('coverage fixture has valid FHIR structure', function (): void {
    $coverage = json_decode(file_get_contents(__DIR__.'/../../Fixtures/coverage.json'), true);

    expect($coverage['resourceType'])->toBe('Coverage')
        ->and($coverage['id'])->toBe('66001')
        ->and($coverage['status'])->toBe('active')
        ->and($coverage['beneficiary'])->toBeArray()
        ->and($coverage['beneficiary']['reference'])->toBe('Patient/12345')
        ->and($coverage['payor'])->toBeArray()
        ->and($coverage['period'])->toBeArray();
});

test('coverage has valid period', function (): void {
    $coverage = json_decode(file_get_contents(__DIR__.'/../../Fixtures/coverage.json'), true);

    expect($coverage['period']['start'])->toBe('2024-01-01')
        ->and($coverage['period']['end'])->toBe('2024-12-31');
});
