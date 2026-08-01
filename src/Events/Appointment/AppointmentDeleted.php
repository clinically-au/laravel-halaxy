<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Events\Appointment;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\WebhookClient\Models\WebhookCall;

final class AppointmentDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $resourceReference,
        public readonly ?string $timestamp,
        public readonly WebhookCall $webhookCall,
    ) {}

    /**
     * Get the appointment ID.
     */
    public function appointmentId(): ?string
    {
        $parts = explode('/', $this->resourceReference);

        return end($parts) ?: null;
    }
}
