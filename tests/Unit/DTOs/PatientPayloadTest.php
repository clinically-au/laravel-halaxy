<?php

declare(strict_types=1);

use Clinically\Halaxy\DTOs\ContactPoint;
use Clinically\Halaxy\DTOs\PatientPayload;

test('serializes the writable Halaxy patient contract without empty properties', function (): void {
    $payload = new PatientPayload(
        name: [[
            'use' => 'official',
            'family' => 'Citizen',
            'given' => ['Jane'],
        ]],
        telecom: [
            ContactPoint::mobile('+61412345678'),
            ContactPoint::email('jane@example.com'),
        ],
        gender: 'female',
        birthDate: '1988-06-01',
        deceasedBoolean: false,
    );

    expect($payload->toArray())->toBe([
        'resourceType' => 'Patient',
        'name' => [[
            'use' => 'official',
            'family' => 'Citizen',
            'given' => ['Jane'],
        ]],
        'telecom' => [[
            'system' => 'sms',
            'value' => '+61412345678',
            'use' => 'mobile',
        ], [
            'system' => 'email',
            'value' => 'jane@example.com',
            'use' => 'home',
        ]],
        'gender' => 'female',
        'birthDate' => '1988-06-01',
        'deceasedBoolean' => false,
    ]);
});

test('omits absent optional patient properties', function (): void {
    expect(new PatientPayload(name: [])->toArray())->toBe([
        'resourceType' => 'Patient',
    ]);
});
