<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Resources\Clinical\DocumentReferenceResource;
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

test('documentReferences returns DocumentReferenceResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->documentReferences())->toBeInstanceOf(DocumentReferenceResource::class);
});

test('can create a document reference', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/DocumentReference' => Http::response([
            'resourceType' => 'DocumentReference',
            'id' => 'DOC-002',
            'status' => 'current',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->documentReferences()->create([
        'status' => 'current',
        'subject' => ['reference' => 'Patient/12345'],
        'content' => [
            [
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'data' => base64_encode('test pdf content'),
                    'title' => 'Test Document.pdf',
                ],
            ],
        ],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('DOC-002');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'DocumentReference')
            && ($body['resourceType'] ?? null) === 'DocumentReference';
    });
});

test('create auto-adds resourceType if missing', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/DocumentReference' => Http::response([
            'resourceType' => 'DocumentReference',
            'id' => 'DOC-002',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->documentReferences()->create([
        'status' => 'current',
        'subject' => ['reference' => 'Patient/12345'],
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['resourceType']) && $body['resourceType'] === 'DocumentReference';
    });
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/DocumentReference' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/document-reference.json'), true),
            201,
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->documentReferences()->create([
        'status' => 'current',
    ]);

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('DocumentReference');
});

test('document reference fixture has valid FHIR structure', function (): void {
    $doc = json_decode(file_get_contents(__DIR__.'/../../Fixtures/document-reference.json'), true);

    expect($doc['resourceType'])->toBe('DocumentReference')
        ->and($doc['id'])->toBe('DOC-001')
        ->and($doc['status'])->toBe('current')
        ->and($doc['subject'])->toBeArray()
        ->and($doc['subject']['reference'])->toBe('Patient/12345')
        ->and($doc['author'])->toBeArray()
        ->and($doc['content'])->toBeArray();
});

test('document reference has attachment', function (): void {
    $doc = json_decode(file_get_contents(__DIR__.'/../../Fixtures/document-reference.json'), true);

    $attachment = $doc['content'][0]['attachment'];

    expect($attachment['contentType'])->toBe('application/pdf')
        ->and($attachment['url'])->toContain('Binary')
        ->and($attachment['title'])->toContain('Consultation Notes');
});

test('document reference has author', function (): void {
    $doc = json_decode(file_get_contents(__DIR__.'/../../Fixtures/document-reference.json'), true);

    expect($doc['author'][0]['reference'])->toBe('Practitioner/99001')
        ->and($doc['author'][0]['display'])->toBe('Dr. Sarah Wilson');
});
