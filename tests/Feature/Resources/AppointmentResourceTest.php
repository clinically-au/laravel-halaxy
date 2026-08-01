<?php

declare(strict_types=1);

use Clinically\Halaxy\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Scheduling\AppointmentResource;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Fake the OAuth token request
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
    ]);
});

test('appointments returns AppointmentResource', function (): void {
    $halaxy = app(Halaxy::class);

    expect($halaxy->appointments())->toBeInstanceOf(AppointmentResource::class);
});

test('can find an appointment by id', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/55001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/appointment.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->find('55001');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'])->toBe('Appointment')
        ->and($response->json()['id'])->toBe('55001')
        ->and($response->json()['status'])->toBe('booked')
        ->and($response->json()['minutesDuration'])->toBe(30);
});

test('can list appointments', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/appointment-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->list();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(2)
        ->and($response->entries())->toHaveCount(2);
});

test('can list appointments with query parameters', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment?status=booked&_count=10' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Appointment', 'id' => '55001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->list(['status' => 'booked', '_count' => 10]);

    expect($response->successful())->toBeTrue()
        ->and($response->total())->toBe(1);
});

test('can book an appointment', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/$book' => Http::response([
            'resourceType' => 'Appointment',
            'id' => '55003',
            'status' => 'booked',
            'start' => '2024-03-01T10:00:00+11:00',
            'end' => '2024-03-01T10:30:00+11:00',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->book([
        'parameter' => [
            [
                'name' => 'appt-resource',
                'resource' => [
                    'resourceType' => 'Appointment',
                    'start' => '2024-03-01T10:00:00+11:00',
                    'end' => '2024-03-01T10:30:00+11:00',
                    'minutesDuration' => 30,
                    'participant' => [
                        ['actor' => ['reference' => 'PractitionerRole/99001']],
                    ],
                ],
            ],
            ['name' => 'patient-id', 'valueReference' => ['reference' => 'Patient/12345']],
            ['name' => 'healthcare-service-id', 'valueReference' => ['reference' => 'HealthcareService/1']],
            ['name' => 'location-type', 'valueCode' => 'clinic'],
            ['name' => 'status', 'valueCode' => 'booked'],
        ],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->status())->toBe(201)
        ->and($response->json()['id'])->toBe('55003')
        ->and($response->json()['status'])->toBe('booked');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && str_contains($request->url(), 'Appointment/$book')
            && ($body['resourceType'] ?? null) === 'Parameters';
    });
});

test('book auto-adds Parameters resourceType if missing', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/$book' => Http::response([
            'resourceType' => 'Appointment',
            'id' => '55003',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->appointments()->book([
        'parameter' => [
            ['name' => 'patient-id', 'valueReference' => ['reference' => 'Patient/1']],
        ],
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return isset($body['resourceType']) && $body['resourceType'] === 'Parameters';
    });
});

test('create is an alias for book', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/$book' => Http::response([
            'resourceType' => 'Appointment',
            'id' => '55003',
        ], 201),
    ]);

    $halaxy = app(Halaxy::class);
    $halaxy->appointments()->create([
        'start' => '2024-03-01T10:00:00+11:00',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'Appointment/$book');
    });
});

test('can find available slots', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/$find?*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 5,
            'entry' => [
                ['resource' => ['resourceType' => 'Slot', 'id' => '1', 'status' => 'free']],
                ['resource' => ['resourceType' => 'Slot', 'id' => '2', 'status' => 'free']],
                ['resource' => ['resourceType' => 'Slot', 'id' => '3', 'status' => 'free']],
                ['resource' => ['resourceType' => 'Slot', 'id' => '4', 'status' => 'free']],
                ['resource' => ['resourceType' => 'Slot', 'id' => '5', 'status' => 'free']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->findAvailable([
        'practitioner' => 'Practitioner/99001',
        'start' => '2024-03-01',
        'end' => '2024-03-07',
        'duration' => '30',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue()
        ->and($response->total())->toBe(5);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), 'Appointment/$find?');
    });
});

test('can update an appointment', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/55001' => Http::response([
            'resourceType' => 'Appointment',
            'id' => '55001',
            'status' => 'cancelled',
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->update('55001', [
        'status' => 'cancelled',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->json()['status'])->toBe('cancelled');

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'Appointment/55001');
    });
});

test('can use query builder to search appointments', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment*' => Http::response([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 1,
            'entry' => [
                ['resource' => ['resourceType' => 'Appointment', 'id' => '55001']],
            ],
        ]),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()
        ->query()
        ->where('status', 'booked')
        ->where('date', 'ge', '2024-02-01')
        ->where('date', 'le', '2024-02-28')
        ->where('actor', 'Practitioner/99001')
        ->orderBy('date')
        ->limit(50)
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'status=booked')
            && str_contains($url, '_sort=date')
            && str_contains($url, '_count=50');
    });
});

test('response provides helper methods', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment/55001' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/appointment.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->find('55001');

    expect($response->isFhirResource())->toBeTrue()
        ->and($response->isBundle())->toBeFalse()
        ->and($response->resourceType())->toBe('Appointment');
});

test('bundle response provides pagination info', function (): void {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]),
        '*/Appointment' => Http::response(
            json_decode(file_get_contents(__DIR__.'/../../Fixtures/appointment-bundle.json'), true),
        ),
    ]);

    $halaxy = app(Halaxy::class);
    $response = $halaxy->appointments()->list();

    expect($response->hasNextPage())->toBeTrue()
        ->and($response->nextPageUrl())->toBe('https://au-api.halaxy.com/main/Appointment?_count=50&page=2')
        ->and($response->links())->toHaveKey('self')
        ->and($response->links())->toHaveKey('next');
});

test('appointment fixture has valid FHIR structure', function (): void {
    $appointment = json_decode(file_get_contents(__DIR__.'/../../Fixtures/appointment.json'), true);

    expect($appointment['resourceType'])->toBe('Appointment')
        ->and($appointment['id'])->toBe('55001')
        ->and($appointment['status'])->toBe('booked')
        ->and($appointment['start'])->toContain('2024-02-01')
        ->and($appointment['end'])->toContain('2024-02-01')
        ->and($appointment['minutesDuration'])->toBe(30)
        ->and($appointment['participant'])->toBeArray()
        ->and($appointment['participant'])->toHaveCount(2);
});

test('appointment has patient and practitioner participants', function (): void {
    $appointment = json_decode(file_get_contents(__DIR__.'/../../Fixtures/appointment.json'), true);

    $patient = collect($appointment['participant'])->first(function ($p) {
        return str_contains($p['actor']['reference'] ?? '', 'Patient');
    });

    $practitioner = collect($appointment['participant'])->first(function ($p) {
        return str_contains($p['actor']['reference'] ?? '', 'Practitioner');
    });

    expect($patient)->not->toBeNull()
        ->and($patient['actor']['reference'])->toBe('Patient/12345')
        ->and($practitioner)->not->toBeNull()
        ->and($practitioner['actor']['reference'])->toBe('Practitioner/99001');
});
