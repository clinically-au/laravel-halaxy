<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('disabling webhooks registers neither routes nor spatie configs', function (): void {
    $webhookRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

    $halaxyConfigs = collect(config('webhook-client.configs', []))
        ->filter(fn (array $config): bool => str_starts_with($config['name'], 'halaxy'));

    expect($webhookRoutes)->toHaveCount(0)
        ->and($halaxyConfigs)->toHaveCount(0);
});
