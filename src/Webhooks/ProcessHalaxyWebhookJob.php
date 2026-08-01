<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Webhooks;

use Clinically\Halaxy\Events\Appointment\AppointmentCreated;
use Clinically\Halaxy\Events\Appointment\AppointmentDeleted;
use Clinically\Halaxy\Events\Appointment\AppointmentUpdated;
use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Clinically\Halaxy\Events\Invoice\InvoiceCreated;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Invoice\InvoiceUpdated;
use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Events\Patient\PatientUpdated;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

final class ProcessHalaxyWebhookJob extends ProcessWebhookJob
{
    /**
     * Halaxy payloads don't identify the action, so the event type is encoded
     * in the endpoint path — and therefore in the webhook config name stored
     * on the WebhookCall.
     *
     * @var array<string, class-string>
     */
    private const array EVENT_MAP = [
        'halaxy-patient-created' => PatientCreated::class,
        'halaxy-patient-updated' => PatientUpdated::class,
        'halaxy-appointment-created' => AppointmentCreated::class,
        'halaxy-appointment-updated' => AppointmentUpdated::class,
        'halaxy-appointment-deleted' => AppointmentDeleted::class,
        'halaxy-invoice-created' => InvoiceCreated::class,
        'halaxy-invoice-updated' => InvoiceUpdated::class,
        'halaxy-invoice-deleted' => InvoiceDeleted::class,
    ];

    public function __construct(WebhookCall $webhookCall)
    {
        parent::__construct($webhookCall);

        $connection = config('halaxy.webhooks.queue_connection');

        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $queue = config('halaxy.webhooks.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    /**
     * Process the webhook.
     */
    public function handle(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->webhookCall->payload ?? [];

        $resourceReference = $this->extractResourceReference($payload);
        $timestamp = $this->extractTimestamp($payload);

        $eventClass = self::EVENT_MAP[$this->webhookCall->name] ?? null;

        if ($eventClass !== null) {
            event(new $eventClass($resourceReference, $timestamp, $this->webhookCall));

            return;
        }

        event(new HalaxyWebhookReceived(
            $this->determineEventType($payload),
            $resourceReference,
            $timestamp,
            $this->webhookCall,
        ));
    }

    /**
     * Best-effort event type for webhooks on the generic endpoint: the payload
     * identifies the resource (focus.type) but never the action.
     *
     * @param  array<string, mixed>  $payload
     */
    private function determineEventType(array $payload): string
    {
        $focus = $payload['entry'][0]['resource']['notificationEvent'][0]['focus'] ?? [];

        $type = $focus['type'] ?? 'unknown';
        $resourceType = strtolower(is_string($type) ? $type : 'unknown');

        return "{$resourceType}.unknown";
    }

    /**
     * Extract the resource reference from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractResourceReference(array $payload): string
    {
        $focus = $payload['entry'][0]['resource']['notificationEvent'][0]['focus'] ?? [];

        $reference = $focus['reference'] ?? '';

        return is_string($reference) ? $reference : '';
    }

    /**
     * Extract the timestamp from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractTimestamp(array $payload): ?string
    {
        $timestamp = $payload['timestamp']
            ?? $payload['entry'][0]['resource']['notificationEvent'][0]['timestamp']
            ?? null;

        return is_string($timestamp) ? $timestamp : null;
    }
}
