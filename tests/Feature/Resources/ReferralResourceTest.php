<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\ReferralResource;
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

test('referrals returns ReferralResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->referrals())->toBeInstanceOf(ReferralResource::class);
});

test('can find a referral by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Referral/REF-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/referral.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referrals()->find('REF-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Referral')
        ->and($response->json()['id'])->toBe('REF-001')
        ->and($response->json()['status'])->toBe('active');
});

test('can list referrals', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Referral' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'Referral', 'id' => 'REF-001']],
                ['resource' => ['resourceType' => 'Referral', 'id' => 'REF-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referrals()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can create a referral', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Referral' => Http::response([
            'resourceType' => 'Referral',
            'id' => 'REF-002',
            'status' => 'active',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referrals()->create([
        'status' => 'active',
        'subject' => ['reference' => 'Patient/12345'],
        'requester' => ['reference' => 'Practitioner/99001'],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('REF-002');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Referral')
            && ($body['resourceType'] ?? null) === 'Referral';
    });
});

test('can update a referral', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Referral/REF-001' => Http::response([
            'resourceType' => 'Referral',
            'id' => 'REF-001',
            'status' => 'completed',
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referrals()->update('REF-001', [
        'status' => 'completed',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->json()['status'])->toBe('completed');

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'Referral/REF-001');
    });
});

test('can use query builder to search referrals', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Referral*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Referral', 'id' => 'REF-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referrals()
        ->query()
        ->where('status', 'active')
        ->where('patient', 'Patient/12345')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('referral fixture has valid FHIR structure', function (): void {
    $ref = json_decode(file_get_contents(__DIR__.'/../../Fixtures/referral.json'), true);

    expect($ref['resourceType'])->toBe('Referral')
        ->and($ref['id'])->toBe('REF-001')
        ->and($ref['status'])->toBe('active')
        ->and($ref['subject'])->toBeArray()
        ->and($ref['requester'])->toBeArray()
        ->and($ref['recipient'])->toBeArray()
        ->and($ref['reasonCode'])->toBeArray();
});

test('referral has patient subject and practitioner requester', function (): void {
    $ref = json_decode(file_get_contents(__DIR__.'/../../Fixtures/referral.json'), true);

    expect($ref['subject']['reference'])->toBe('Patient/12345')
        ->and($ref['requester']['reference'])->toBe('Practitioner/99001')
        ->and($ref['recipient'][0]['reference'])->toBe('Practitioner/99002');
});
