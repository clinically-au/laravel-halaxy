<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\Appointment\AppointmentCreated;
use Clinically\Halaxy\Events\Appointment\AppointmentDeleted;
use Clinically\Halaxy\Events\Appointment\AppointmentUpdated;
use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Clinically\Halaxy\Events\Invoice\InvoiceCreated;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Invoice\InvoiceUpdated;
use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Events\Patient\PatientUpdated;
use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;
use Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

function makeWebhookCall(string $name, ?array $payload = null): WebhookCall
{
    $payload ??= json_decode(
        file_get_contents(__DIR__.'/../../Fixtures/webhook-patient-created.json'),
        true,
    );

    return new WebhookCall(['name' => $name, 'payload' => $payload]);
}

describe('ProcessHalaxyWebhookJob', function (): void {
    test('dispatches the typed event matching the config name', function (string $name, string $eventClass): void {
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall($name)))->handle();

        Event::assertDispatched($eventClass, function (object $event): bool {
            return $event->resourceReference === 'https://au-api.halaxy.com/main/Patient/synthetic-patient-001'
                && $event->timestamp === '2026-01-02T03:04:05+00:00';
        });
    })->with([
        ['halaxy-patient-created', PatientCreated::class],
        ['halaxy-patient-updated', PatientUpdated::class],
        ['halaxy-appointment-created', AppointmentCreated::class],
        ['halaxy-appointment-updated', AppointmentUpdated::class],
        ['halaxy-appointment-deleted', AppointmentDeleted::class],
        ['halaxy-invoice-created', InvoiceCreated::class],
        ['halaxy-invoice-updated', InvoiceUpdated::class],
        ['halaxy-invoice-deleted', InvoiceDeleted::class],
    ]);

    test('generic config name dispatches HalaxyWebhookReceived with resource type from focus', function (): void {
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy')))->handle();

        Event::assertDispatched(HalaxyWebhookReceived::class, function (HalaxyWebhookReceived $event): bool {
            return $event->eventType === 'patient.unknown'
                && $event->resourceId() === 'synthetic-patient-001'
                && $event->resourceType() === 'Patient';
        });
    });

    test('unrecognised config name falls back to HalaxyWebhookReceived', function (): void {
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy-something-new')))->handle();

        Event::assertDispatched(HalaxyWebhookReceived::class);
    });

    test('every registered event config name maps to a typed event, not the generic fallback', function (string $name): void {
        // Pins ProcessHalaxyWebhookJob::EVENT_MAP against HalaxyWebhookRoutes::EVENTS
        // drifting apart: if a config name were ever missing from EVENT_MAP, the
        // job would silently fall back to dispatching HalaxyWebhookReceived here.
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall($name)))->handle();

        Event::assertNotDispatched(HalaxyWebhookReceived::class);
    })->with(
        array_values(array_filter(
            HalaxyWebhookRoutes::configNames(),
            static fn (string $name): bool => $name !== 'halaxy',
        )),
    );

    test('applies the configured queue connection and queue name', function (): void {
        config([
            'halaxy.webhooks.queue_connection' => 'redis',
            'halaxy.webhooks.queue' => 'webhooks',
        ]);

        $job = new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy'));

        expect($job->connection)->toBe('redis')
            ->and($job->queue)->toBe('webhooks');
    });

    test('leaves queue connection and name at their defaults when unconfigured', function (): void {
        $job = new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy'));

        expect($job->connection)->toBeNull()
            ->and($job->queue)->toBeNull();
    });

    test('malformed payload falls back to safe defaults instead of throwing', function (): void {
        Event::fake();

        $payload = [
            'entry' => [[
                'resource' => [
                    'notificationEvent' => [[
                        'focus' => [
                            'type' => ['weird'],
                            'reference' => 123,
                        ],
                    ]],
                ],
            ]],
            'timestamp' => ['x'],
        ];

        (new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy', $payload)))->handle();

        Event::assertDispatched(HalaxyWebhookReceived::class, function (HalaxyWebhookReceived $event): bool {
            return $event->eventType === 'unknown.unknown'
                && $event->resourceReference === ''
                && $event->timestamp === null;
        });
    });
});
