<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\WebhookClient\Models\WebhookCall;

beforeEach(function (): void {
    $this->migrateWebhookCallsTable();
    $this->payload = json_decode($this->fixture('webhook-patient-created.json'), true);
});

describe('Webhook Pipeline', function (): void {
    test('event endpoint stores the call and dispatches the typed event', function (): void {
        Event::fake([PatientCreated::class]);

        $this->postJson('/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        expect(WebhookCall::query()->count())->toBe(1)
            ->and(WebhookCall::query()->first()->name)->toBe('halaxy-patient-created');

        Event::assertDispatched(PatientCreated::class, function (PatientCreated $event): bool {
            return $event->resourceReference === 'https://au-api.halaxy.com/main/Patient/synthetic-patient-001'
                && $event->timestamp === '2026-01-02T03:04:05+00:00'
                && $event->patientId() === 'synthetic-patient-001';
        });
    });

    test('the action comes from the URL even for another resource endpoint', function (): void {
        Event::fake([InvoiceDeleted::class]);

        $this->postJson('/webhooks/halaxy/invoice-deleted', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(InvoiceDeleted::class);
    });

    test('generic endpoint dispatches HalaxyWebhookReceived', function (): void {
        Event::fake([HalaxyWebhookReceived::class]);

        $this->postJson('/webhooks/halaxy', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(HalaxyWebhookReceived::class, function (HalaxyWebhookReceived $event): bool {
            return $event->eventType === 'patient.unknown'
                && $event->resourceType() === 'Patient'
                && $event->resourceId() === 'synthetic-patient-001';
        });
    });

    test('Bearer-prefixed secret is accepted', function (): void {
        Event::fake([PatientCreated::class]);

        $this->postJson('/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'Bearer test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(PatientCreated::class);
    });

    test('wrong secret is rejected and nothing is stored or dispatched', function (): void {
        Event::fake();

        $response = $this->postJson('/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'wrong-secret',
        ]);

        expect($response->status())->toBeGreaterThanOrEqual(400)
            ->and(WebhookCall::query()->count())->toBe(0);

        Event::assertNotDispatched(PatientCreated::class);
    });

    test('tenant-style group registration processes webhooks end to end', function (): void {
        Event::fake([PatientCreated::class]);

        Route::prefix('app/{tenant}/webhooks')->group(function (): void {
            HalaxyWebhookRoutes::register('halaxy');
        });

        $this->postJson('/app/acme/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(PatientCreated::class);
    });
});
