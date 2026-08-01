<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('no webhook routes are registered when register_routes is false', function (): void {
    $webhookRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

    expect($webhookRoutes)->toHaveCount(0);
});
