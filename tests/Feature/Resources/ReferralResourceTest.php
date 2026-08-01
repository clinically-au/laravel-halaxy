<?php

declare(strict_types=1);

use Clinically\Halaxy\DTOs\ReferralAttachment;
use Clinically\Halaxy\DTOs\ReferralPayload;
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
    $response = $halaxy->referrals()->create(new ReferralPayload(
        coverageReference: 'Coverage/66001',
        subjectReference: 'Patient/12345',
        requesterReference: 'PractitionerRole/EP-99001',
        created: new DateTimeImmutable('2026-08-01T10:30:00+10:00'),
        comment: 'Specialist review requested',
        attachments: [
            new ReferralAttachment('application/pdf', 'cGRmLWRhdGE=', 'referral.pdf'),
        ],
    ));

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('REF-002');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Referral')
            && $body === [
                'resourceType' => 'Referral',
                'coverage' => ['reference' => 'Coverage/66001', 'type' => 'Coverage'],
                'requester' => ['reference' => 'PractitionerRole/EP-99001', 'type' => 'PractitionerRole'],
                'created' => '2026-08-01T10:30:00+10:00',
                'active' => true,
                'subject' => ['reference' => 'Patient/12345', 'type' => 'Patient'],
                'comment' => 'Specialist review requested',
                'attachments' => [[
                    'contentType' => 'application/pdf',
                    'data' => 'cGRmLWRhdGE=',
                    'title' => 'referral.pdf',
                ]],
            ];
    });
});

test('can append attachments to a referral', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Referral/REF-001' => Http::response([
            'resourceType' => 'Referral',
            'id' => 'REF-001',
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->referrals()->addAttachments(
        'REF-001',
        new ReferralAttachment('application/pdf', 'cGRmLWRhdGE=', 'referral.pdf'),
    );

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request): bool {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'Referral/REF-001')
            && json_decode($request->body(), true) === [
                'resourceType' => 'Referral',
                'attachments' => [[
                    'contentType' => 'application/pdf',
                    'data' => 'cGRmLWRhdGE=',
                    'title' => 'referral.pdf',
                ]],
            ];
    });
});

test('rejects unsupported referral updates', function (): void {
    $halaxy = app(Halaxy::class);

    expect(fn () => $halaxy->referrals()->update('REF-001', ['active' => false]))
        ->toThrow(InvalidArgumentException::class, 'only support attachment updates');
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
