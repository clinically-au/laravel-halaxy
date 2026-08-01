<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Scheduling\ScheduleResource;
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

test('schedules returns ScheduleResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->schedules())->toBeInstanceOf(ScheduleResource::class);
});

test('can find a schedule by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Schedule/33001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/schedule.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()->find('33001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Schedule')
        ->and($response->json()['id'])->toBe('33001')
        ->and($response->json()['active'])->toBeTrue();
});

test('can list schedules', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Schedule' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/schedule-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list schedules with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Schedule?*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Schedule', 'id' => '33001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()->list(['actor' => 'Practitioner/99001', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can create a schedule', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Schedule' => Http::response([
            'resourceType' => 'Schedule',
            'id' => '33003',
            'active' => true,
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()->create([
        'active' => true,
        'actor' => [
            ['reference' => 'Practitioner/99001'],
        ],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('33003');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Schedule')
            && ($body['resourceType'] ?? null) === 'Schedule';
    });
});

test('can generate slots for a schedule', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Schedule/33001/$generate' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'total' => 10,
            'entry' => array_fill(0, 10, [
                'resource' => ['resourceType' => 'Slot', 'status' => 'free'],
            ]),
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()->generateSlots('33001', [
        'period' => [
            'start' => '2024-02-01T09:00:00+11:00',
            'end' => '2024-02-07T17:00:00+11:00',
        ],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->json()['total'])->toBe(10);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'Schedule/33001/$generate');
    });
});

test('can use query builder to search schedules', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Schedule*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Schedule', 'id' => '33001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()
        ->query()
        ->where('actor', 'Practitioner/99001')
        ->where('active', 'true')
        ->where('date', 'ge', '2024-02-01')
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
        '*/Schedule/33001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/schedule.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->schedules()->find('33001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Schedule');
});

test('schedule fixture has valid FHIR structure', function (): void {
    $schedule = json_decode(file_get_contents(__DIR__.'/../../Fixtures/schedule.json'), true);

    expect($schedule['resourceType'])->toBe('Schedule')
        ->and($schedule['id'])->toBe('33001')
        ->and($schedule['active'])->toBeTrue()
        ->and($schedule['actor'])->toBeArray()
        ->and($schedule['planningHorizon'])->toBeArray()
        ->and($schedule['planningHorizon']['start'])->toContain('2024-02-01')
        ->and($schedule['planningHorizon']['end'])->toContain('2024-02-28');
});

test('schedule has practitioner actor', function (): void {
    $schedule = json_decode(file_get_contents(__DIR__.'/../../Fixtures/schedule.json'), true);

    $practitioner = collect($schedule['actor'])->first(function ($actor) {
        return str_contains($actor['reference'] ?? '', 'Practitioner');
    });

    expect($practitioner)->not->toBeNull()
        ->and($practitioner['reference'])->toBe('Practitioner/99001');
});
