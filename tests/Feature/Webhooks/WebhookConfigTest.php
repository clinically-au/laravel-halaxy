<?php

declare(strict_types=1);

use Clinically\Halaxy\Webhooks\HalaxySignatureValidator;
use Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;

describe('Webhook Config', function (): void {
    test('registers one spatie config per endpoint', function (): void {
        $names = collect(config('webhook-client.configs'))->pluck('name');

        expect($names)->toContain('halaxy')
            ->toContain('halaxy-patient-created')
            ->toContain('halaxy-patient-updated')
            ->toContain('halaxy-appointment-created')
            ->toContain('halaxy-appointment-updated')
            ->toContain('halaxy-appointment-deleted')
            ->toContain('halaxy-invoice-created')
            ->toContain('halaxy-invoice-updated')
            ->toContain('halaxy-invoice-deleted');
    });

    test('entries use the package defaults', function (): void {
        $config = collect(config('webhook-client.configs'))
            ->firstWhere('name', 'halaxy-patient-created');

        expect($config['signature_validator'])->toBe(HalaxySignatureValidator::class)
            ->and($config['webhook_profile'])->toBe(ProcessEverythingWebhookProfile::class)
            ->and($config['process_webhook_job'])->toBe(ProcessHalaxyWebhookJob::class)
            ->and($config['webhook_model'])->toBe(WebhookCall::class)
            ->and($config['signature_header_name'])->toBe('Authorization');
    });
});
