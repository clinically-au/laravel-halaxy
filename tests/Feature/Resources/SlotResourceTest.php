<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Scheduling\SlotResource;
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

test('slots returns SlotResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->slots())->toBeInstanceOf(SlotResource::class);
});

test('can find a slot by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Slot/44001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/slot.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->slots()->find('44001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Slot')
        ->and($response->json()['id'])->toBe('44001')
        ->and($response->json()['status'])->toBe('free');
});

test('can list slots', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Slot' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/slot-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->slots()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(3)
        ->and($response->entries())->toHaveCount(3);
});

test('can list slots with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Slot?status=free&_count=10' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 2,
            'entry' => [
                ['resource' => ['resourceType' => 'Slot', 'id' => '44001']],
                ['resource' => ['resourceType' => 'Slot', 'id' => '44002']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->slots()->list(['status' => 'free', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(2);
});

test('can use query builder to search slots', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Slot*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 5,
            'entry' => array_fill(0, 5, [
                'resource' => ['resourceType' => 'Slot', 'status' => 'free'],
            ]),
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->slots()
        ->query()
        ->where('schedule', 'Schedule/33001')
        ->where('status', 'free')
        ->where('start', 'ge', '2024-02-01T00:00:00+11:00')
        ->where('start', 'le', '2024-02-07T23:59:59+11:00')
        ->orderBy('start')
        ->limit(50)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(5);
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Slot/44001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/slot.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->slots()->find('44001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Slot');
});

test('slot fixture has valid FHIR structure', function (): void {
    $slot = json_decode(file_get_contents(__DIR__.'/../../Fixtures/slot.json'), true);

    expect($slot['resourceType'])->toBe('Slot')
        ->and($slot['id'])->toBe('44001')
        ->and($slot['status'])->toBe('free')
        ->and($slot['schedule'])->toBeArray()
        ->and($slot['schedule']['reference'])->toBe('Schedule/33001')
        ->and($slot['start'])->toContain('2024-02-01T09:00')
        ->and($slot['end'])->toContain('2024-02-01T09:30');
});

test('slot bundle has free and busy slots', function (): void {
    $bundle = json_decode(file_get_contents(__DIR__.'/../../Fixtures/slot-bundle.json'), true);

    $freeSlots = collect($bundle['entry'])->filter(function ($entry) {
        return ($entry['resource']['status'] ?? '') === 'free';
    });

    $busySlots = collect($bundle['entry'])->filter(function ($entry) {
        return ($entry['resource']['status'] ?? '') === 'busy';
    });

    expect($freeSlots)->toHaveCount(2)
        ->and($busySlots)->toHaveCount(1);
});
