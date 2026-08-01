<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\ReferralDefinitionResource;
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

test('referralDefinitions returns ReferralDefinitionResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->referralDefinitions())->toBeInstanceOf(ReferralDefinitionResource::class);
});

test('can find a referral definition by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/ReferralDefinition/RD-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/referral-definition.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referralDefinitions()->find('RD-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('ReferralDefinition')
        ->and($response->json()['id'])->toBe('RD-001')
        ->and($response->json()['status'])->toBe('active');
});

test('can list referral definitions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/ReferralDefinition' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'ReferralDefinition', 'id' => 'RD-001']],
                ['resource' => ['resourceType' => 'ReferralDefinition', 'id' => 'RD-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referralDefinitions()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can use query builder to search referral definitions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/ReferralDefinition*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'ReferralDefinition', 'id' => 'RD-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referralDefinitions()
        ->query()
        ->where('status', 'active')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('referral definition fixture has valid FHIR structure', function (): void {
    $rd = json_decode(file_get_contents(__DIR__.'/../../Fixtures/referral-definition.json'), true);

    expect($rd['resourceType'])->toBe('ReferralDefinition')
        ->and($rd['id'])->toBe('RD-001')
        ->and($rd['status'])->toBe('active')
        ->and($rd['name'])->toBe('Standard Referral')
        ->and($rd['title'])->toBe('Standard Medical Referral')
        ->and($rd['code'])->toBeArray();
});
