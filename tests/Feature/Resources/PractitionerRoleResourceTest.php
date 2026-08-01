<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\People\PractitionerRoleResource;
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

test('practitionerRoles returns PractitionerRoleResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->practitionerRoles())->toBeInstanceOf(PractitionerRoleResource::class);
});

test('can find a practitioner role by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PractitionerRole/PR-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner-role.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitionerRoles()->find('PR-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('PractitionerRole')
        ->and($response->json()['id'])->toBe('PR-001')
        ->and($response->json()['active'])->toBeTrue();
});

test('can list practitioner roles', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PractitionerRole' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'PractitionerRole', 'id' => 'PR-001']],
                ['resource' => ['resourceType' => 'PractitionerRole', 'id' => 'PR-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitionerRoles()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can list practitioner roles with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PractitionerRole?*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'PractitionerRole', 'id' => 'PR-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitionerRoles()->list(['practitioner' => 'Practitioner/99001']);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can create a practitioner role', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PractitionerRole' => Http::response([
            'resourceType' => 'PractitionerRole',
            'id' => 'PR-002',
            'active' => true,
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitionerRoles()->create([
        'active' => true,
        'practitioner' => ['reference' => 'Practitioner/99001'],
        'organization' => ['reference' => 'Organization/88001'],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('PR-002');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'PractitionerRole')
            && ($body['resourceType'] ?? null) === 'PractitionerRole';
    });
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PractitionerRole/PR-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner-role.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->practitionerRoles()->find('PR-001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('PractitionerRole');
});

test('practitioner role fixture has valid FHIR structure', function (): void {
    $role = json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner-role.json'), true);

    expect($role['resourceType'])->toBe('PractitionerRole')
        ->and($role['id'])->toBe('PR-001')
        ->and($role['active'])->toBeTrue()
        ->and($role['practitioner'])->toBeArray()
        ->and($role['organization'])->toBeArray()
        ->and($role['code'])->toBeArray()
        ->and($role['specialty'])->toBeArray();
});

test('practitioner role has practitioner and organization references', function (): void {
    $role = json_decode(file_get_contents(__DIR__.'/../../Fixtures/practitioner-role.json'), true);

    expect($role['practitioner']['reference'])->toBe('Practitioner/99001')
        ->and($role['organization']['reference'])->toBe('Organization/88001');
});
