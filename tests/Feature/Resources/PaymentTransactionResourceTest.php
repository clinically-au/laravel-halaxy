<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Financial\PaymentTransactionResource;
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

test('paymentTransactions returns PaymentTransactionResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->paymentTransactions())->toBeInstanceOf(PaymentTransactionResource::class);
});

test('can find a payment transaction by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PaymentTransaction/PT-001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/payment-transaction.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->paymentTransactions()->find('PT-001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('PaymentTransaction')
        ->and($response->json()['id'])->toBe('PT-001')
        ->and($response->json()['status'])->toBe('completed');
});

test('can list payment transactions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PaymentTransaction' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'PaymentTransaction', 'id' => 'PT-001']],
                ['resource' => ['resourceType' => 'PaymentTransaction', 'id' => 'PT-002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->paymentTransactions()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can use query builder to search payment transactions', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/PaymentTransaction*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'PaymentTransaction', 'id' => 'PT-001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->paymentTransactions()
        ->query()
        ->where('status', 'completed')
        ->where('payment-date', 'ge', '2024-01-01')
        ->limit(25)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

test('payment transaction fixture has valid FHIR structure', function (): void {
    $pt = json_decode(file_get_contents(__DIR__.'/../../Fixtures/payment-transaction.json'), true);

    expect($pt['resourceType'])->toBe('PaymentTransaction')
        ->and($pt['id'])->toBe('PT-001')
        ->and($pt['status'])->toBe('completed')
        ->and($pt['payment'])->toBeArray()
        ->and($pt['paymentDate'])->toBe('2024-01-26')
        ->and($pt['request']['reference'])->toBe('Invoice/77001');
});
