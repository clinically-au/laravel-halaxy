<?php

declare(strict_types=1);

use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;

test('host-configured classes override the package defaults', function (): void {
    $config = collect(config('webhook-client.configs'))
        ->firstWhere('name', 'halaxy-patient-created');

    expect($config['signature_validator'])->toBe(DefaultSignatureValidator::class);
});
