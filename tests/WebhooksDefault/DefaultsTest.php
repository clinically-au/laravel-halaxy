<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('webhooks are disabled by default', function (): void {
    $webhookRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

    $halaxyConfigs = collect(config('webhook-client.configs', []))
        ->filter(fn (array $config): bool => str_starts_with($config['name'], 'halaxy'));

    expect(config('halaxy.webhooks.enabled'))->toBeFalse()
        ->and($webhookRoutes)->toHaveCount(0)
        ->and($halaxyConfigs)->toHaveCount(0);

    $this->postJson('/webhooks/halaxy')->assertNotFound();
});
