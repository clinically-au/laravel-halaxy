<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\WebhookClient\Models\WebhookCall;

final class HalaxyWebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $eventType,
        public readonly string $resourceReference,
        public readonly ?string $timestamp,
        public readonly WebhookCall $webhookCall,
    ) {}

    /**
     * Extract the resource ID from the reference.
     */
    public function resourceId(): ?string
    {
        // Reference format: "https://au-api.halaxy.com/main/Patient/123456789"
        // or "Patient/123456789"
        $parts = explode('/', $this->resourceReference);

        return end($parts) ?: null;
    }

    /**
     * Extract the resource type from the reference.
     */
    public function resourceType(): ?string
    {
        // Reference format: "https://au-api.halaxy.com/main/Patient/123456789"
        $parts = explode('/', $this->resourceReference);
        $count = count($parts);

        return $count >= 2 ? $parts[$count - 2] : null;
    }
}
