<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Events\Invoice;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\WebhookClient\Models\WebhookCall;

final class InvoiceDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $resourceReference,
        public readonly ?string $timestamp,
        public readonly WebhookCall $webhookCall,
    ) {}

    /**
     * Get the invoice ID.
     */
    public function invoiceId(): ?string
    {
        $parts = explode('/', $this->resourceReference);

        return end($parts) ?: null;
    }
}
