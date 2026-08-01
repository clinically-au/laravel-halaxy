<?php

declare(strict_types=1);

use Clinically\Halaxy\Enums\Region;

test('AU region has correct value', function (): void {
    expect(Region::AU->value)->toBe('au');
});

test('EU region has correct value', function (): void {
    expect(Region::EU->value)->toBe('eu');
});

test('AU region returns correct base URL', function (): void {
    expect(Region::AU->baseUrl())->toBe('https://au-api.halaxy.com/main/');
});

test('EU region returns correct base URL', function (): void {
    expect(Region::EU->baseUrl())->toBe('https://eu-api.halaxy.com/main/');
});

test('fromString creates AU region from lowercase', function (): void {
    expect(Region::fromString('au'))->toBe(Region::AU);
});

test('fromString creates EU region from lowercase', function (): void {
    expect(Region::fromString('eu'))->toBe(Region::EU);
});

test('fromString creates AU region from uppercase', function (): void {
    expect(Region::fromString('AU'))->toBe(Region::AU);
});

test('fromString creates EU region from uppercase', function (): void {
    expect(Region::fromString('EU'))->toBe(Region::EU);
});

test('fromString creates AU region from mixed case', function (): void {
    expect(Region::fromString('Au'))->toBe(Region::AU);
});

test('fromString creates EU region from mixed case', function (): void {
    expect(Region::fromString('Eu'))->toBe(Region::EU);
});

test('fromString defaults to AU for invalid value', function (): void {
    expect(Region::fromString('invalid'))->toBe(Region::AU);
});

test('fromString defaults to AU for empty string', function (): void {
    expect(Region::fromString(''))->toBe(Region::AU);
});

test('fromString defaults to AU for unknown region', function (): void {
    expect(Region::fromString('us'))->toBe(Region::AU);
});

test('tryFrom returns null for invalid value', function (): void {
    expect(Region::tryFrom('invalid'))->toBeNull();
});

test('tryFrom returns AU for au value', function (): void {
    expect(Region::tryFrom('au'))->toBe(Region::AU);
});

test('tryFrom returns EU for eu value', function (): void {
    expect(Region::tryFrom('eu'))->toBe(Region::EU);
});

test('from throws exception for invalid value', function (): void {
    Region::from('invalid');
})->throws(ValueError::class);

test('cases returns all regions', function (): void {
    $cases = Region::cases();

    expect($cases)->toHaveCount(2)
        ->and($cases)->toContain(Region::AU)
        ->and($cases)->toContain(Region::EU);
});

test('base URLs end with trailing slash', function (): void {
    foreach (Region::cases() as $region) {
        expect($region->baseUrl())->toEndWith('/');
    }
});

test('base URLs use HTTPS', function (): void {
    foreach (Region::cases() as $region) {
        expect($region->baseUrl())->toStartWith('https://');
    }
});

test('base URLs contain region code', function (): void {
    expect(Region::AU->baseUrl())->toContain('au-api')
        ->and(Region::EU->baseUrl())->toContain('eu-api');
});
