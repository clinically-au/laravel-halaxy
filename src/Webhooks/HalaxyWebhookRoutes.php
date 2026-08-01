<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Webhooks;

use Illuminate\Support\Facades\Route;

/**
 * Registers the Halaxy webhook endpoints.
 *
 * Halaxy payloads do not identify the action (created/updated/deleted), so
 * each event type gets its own endpoint path; the Halaxy UI webhook for that
 * event must point at the matching URL. Multi-tenant hosts disable auto
 * registration (HALAXY_WEBHOOK_ROUTES=false) and call register() inside
 * their own tenant-prefixed, tenant-middleware route group.
 */
final class HalaxyWebhookRoutes
{
    /**
     * Event slugs, each also the path suffix under the base path.
     */
    public const array EVENTS = [
        'patient-created',
        'patient-updated',
        'appointment-created',
        'appointment-updated',
        'appointment-deleted',
        'invoice-created',
        'invoice-updated',
        'invoice-deleted',
    ];

    /**
     * Register the base (generic) route and one route per event.
     *
     * @param  string|null  $prefix  Path prefix relative to the current route
     *                               group. Defaults to halaxy.webhooks.path.
     */
    public static function register(?string $prefix = null): void
    {
        $prefix = rtrim($prefix ?? config('halaxy.webhooks.path', 'webhooks/halaxy'), '/');

        /** @phpstan-ignore-next-line */
        Route::webhooks($prefix, 'halaxy');

        foreach (self::EVENTS as $event) {
            /** @phpstan-ignore-next-line */
            Route::webhooks("{$prefix}/{$event}", "halaxy-{$event}");
        }
    }

    /**
     * All spatie webhook-client config names the package registers.
     *
     * @return array<int, string>
     */
    public static function configNames(): array
    {
        return ['halaxy', ...array_map(
            static fn (string $event): string => "halaxy-{$event}",
            self::EVENTS,
        )];
    }
}
