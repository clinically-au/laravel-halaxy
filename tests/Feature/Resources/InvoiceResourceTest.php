<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\InvoiceResource;
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

test('invoices returns InvoiceResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->invoices())->toBeInstanceOf(InvoiceResource::class);
});

test('can find an invoice by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Invoice/77001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoices()->find('77001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Invoice')
        ->and($response->json()['id'])->toBe('77001')
        ->and($response->json()['status'])->toBe('issued')
        ->and($response->json()['totalGross']['value'])->toEqual(85.00)
        ->and($response->json()['totalGross']['currency'])->toBe('AUD');
});

test('can list invoices', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Invoice' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoices()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list invoices with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Invoice?status=issued&_count=10' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Invoice', 'id' => '77001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoices()->list(['status' => 'issued', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can use query builder to search invoices', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Invoice*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Invoice', 'id' => '77001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoices()
        ->query()
        ->where('status', 'issued')
        ->where('date', 'ge', '2024-01-01')
        ->where('subject', 'Patient/12345')
        ->orderBy('-date')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'status=issued')
            && str_contains($url, '_sort=-date')
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
        '*/Invoice/77001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoices()->find('77001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Invoice');
});

test('bundle response provides pagination info', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Invoice' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->invoices()->list();

    expect($response->hasNextPage())->toBeTrue()
        ->and($response->nextPageUrl())->toBe('https://au-api.halaxy.com/main/Invoice?_count=50&page=2')
        ->and($response->links())->toHaveKey('self')
        ->and($response->links())->toHaveKey('next');
});

test('invoice fixture has valid FHIR structure', function (): void {
    $invoice = json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice.json'), true);

    expect($invoice['resourceType'])->toBe('Invoice')
        ->and($invoice['id'])->toBe('77001')
        ->and($invoice['status'])->toBe('issued')
        ->and($invoice['date'])->toBe('2024-01-25')
        ->and($invoice['identifier'])->toBeArray()
        ->and($invoice['subject'])->toBeArray()
        ->and($invoice['participant'])->toBeArray()
        ->and($invoice['totalNet'])->toBeArray()
        ->and($invoice['totalGross'])->toBeArray();
});

test('invoice has patient subject reference', function (): void {
    $invoice = json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice.json'), true);

    expect($invoice['subject']['reference'])->toBe('Patient/12345')
        ->and($invoice['subject']['display'])->toBe('John Smith');
});

test('invoice has practitioner participant', function (): void {
    $invoice = json_decode(file_get_contents(__DIR__.'/../../Fixtures/invoice.json'), true);

    $provider = collect($invoice['participant'])->first(function ($p) {
        return ($p['role']['coding'][0]['code'] ?? '') === 'provider';
    });

    expect($provider)->not->toBeNull()
        ->and($provider['actor']['reference'])->toBe('Practitioner/99001');
});
