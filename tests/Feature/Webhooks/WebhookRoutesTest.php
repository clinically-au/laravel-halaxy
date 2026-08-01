<?php

declare(strict_types=1);

use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;
use Illuminate\Support\Facades\Route;

describe('Webhook Routes', function (): void {
    test('auto-registers the base route and one route per event', function (): void {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

        expect($routes)->toHaveCount(9);

        $uris = $routes->map(fn ($route): string => $route->uri())->values()->all();

        expect($uris)->toContain('webhooks/halaxy')
            ->toContain('webhooks/halaxy/patient-created')
            ->toContain('webhooks/halaxy/patient-updated')
            ->toContain('webhooks/halaxy/appointment-created')
            ->toContain('webhooks/halaxy/appointment-updated')
            ->toContain('webhooks/halaxy/appointment-deleted')
            ->toContain('webhooks/halaxy/invoice-created')
            ->toContain('webhooks/halaxy/invoice-updated')
            ->toContain('webhooks/halaxy/invoice-deleted');
    });

    test('routes are named after their spatie config', function (): void {
        expect(Route::getRoutes()->getByName('webhook-client-halaxy'))->not->toBeNull();
        expect(Route::getRoutes()->getByName('webhook-client-halaxy-patient-created'))->not->toBeNull();
        expect(Route::getRoutes()->getByName('webhook-client-halaxy-invoice-deleted'))->not->toBeNull();
    });

    test('register() inside a group inherits its prefix', function (): void {
        Route::prefix('app/{tenant}/webhooks')->group(function (): void {
            HalaxyWebhookRoutes::register('halaxy');
        });

        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri());

        expect($uris)->toContain('app/{tenant}/webhooks/halaxy/appointment-created')
            ->toContain('app/{tenant}/webhooks/halaxy');
    });

    test('configNames returns the base name plus one per event', function (): void {
        expect(HalaxyWebhookRoutes::configNames())->toBe([
            'halaxy',
            'halaxy-patient-created',
            'halaxy-patient-updated',
            'halaxy-appointment-created',
            'halaxy-appointment-updated',
            'halaxy-appointment-deleted',
            'halaxy-invoice-created',
            'halaxy-invoice-updated',
            'halaxy-invoice-deleted',
        ]);
    });
});
