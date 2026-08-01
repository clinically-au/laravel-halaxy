<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\InvoiceLineResource;
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

test('invoiceLines returns InvoiceLineResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->invoiceLines())->toBeInstanceOf(InvoiceLineResource::class);
});

test('can find an invoice line by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/InvoiceLine/IL-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice-line.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoiceLines()->find('IL-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('InvoiceLine')
        ->and($response->json()['id'])->toBe('IL-001')
        ->and($response->json()['sequence'])->toBe(1);
});

test('can list invoice lines', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/InvoiceLine' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'InvoiceLine', 'id' => 'IL-001']],
                ['resource' => ['resourceType' => 'InvoiceLine', 'id' => 'IL-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoiceLines()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can use query builder to search invoice lines', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/InvoiceLine*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'InvoiceLine', 'id' => 'IL-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoiceLines()
        ->query()
        ->where('invoice', 'Invoice/77001')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('invoice line fixture has valid FHIR structure', function (): void {
    $il = json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice-line.json'), true);

    expect($il['resourceType'])->toBe('InvoiceLine')
        ->and($il['id'])->toBe('IL-001')
        ->and($il['sequence'])->toBe(1)
        ->and($il['chargeItemReference'])->toBeArray()
        ->and($il['priceComponent'])->toBeArray();
});
