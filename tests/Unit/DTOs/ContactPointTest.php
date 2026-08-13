<?php

declare(strict_types=1);

use Clinically\Halaxy\DTOs\ContactPoint;
use Clinically\Halaxy\Enums\ContactPointUse;

test('serializes Halaxy patient telecom contact points', function (): void {
    expect(ContactPoint::mobile('+61412345678')->toArray())->toBe([
        'system' => 'sms',
        'value' => '+61412345678',
        'use' => 'mobile',
    ])->and(ContactPoint::phone('+61298765432')->toArray())->toBe([
        'system' => 'phone',
        'value' => '+61298765432',
        'use' => 'home',
    ])->and(ContactPoint::phone('+61298765432', ContactPointUse::Work)->toArray())->toBe([
        'system' => 'phone',
        'value' => '+61298765432',
        'use' => 'work',
    ])->and(ContactPoint::email('patient@example.com')->toArray())->toBe([
        'system' => 'email',
        'value' => 'patient@example.com',
        'use' => 'home',
    ]);
});

test('rejects phone numbers that are not in compact international format', function (string $number): void {
    ContactPoint::mobile($number);
})->with([
    'Australian national format' => '0412345678',
    'international format with spaces' => '+61 412 345 678',
    'missing country code' => '+0412345678',
])->throws(InvalidArgumentException::class, 'Phone numbers sent to Halaxy must use compact international format.');

test('rejects invalid email addresses', function (): void {
    ContactPoint::email('not-an-email');
})->throws(InvalidArgumentException::class, 'Email addresses sent to Halaxy must be valid.');
