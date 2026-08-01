<?php

declare(strict_types=1);

use Clinically\Halaxy\Webhooks\HalaxySignatureValidator;
use Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob;
use Illuminate\Http\Request;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

function validatorConfig(string $secret): WebhookConfig
{
    return new WebhookConfig([
        'name' => 'halaxy',
        'signing_secret' => $secret,
        'signature_header_name' => 'Authorization',
        'signature_validator' => DefaultSignatureValidator::class,
        'webhook_profile' => ProcessEverythingWebhookProfile::class,
        'webhook_response' => DefaultRespondsTo::class,
        'webhook_model' => WebhookCall::class,
        'store_headers' => [],
        'process_webhook_job' => ProcessHalaxyWebhookJob::class,
    ]);
}

function requestWithHeader(?string $value): Request
{
    $request = Request::create('/webhooks/halaxy', 'POST');

    if ($value !== null) {
        $request->headers->set('Authorization', $value);
    }

    return $request;
}

describe('HalaxySignatureValidator', function (): void {
    test('accepts an exact secret match', function (): void {
        expect((new HalaxySignatureValidator)->isValid(requestWithHeader('s3cret'), validatorConfig('s3cret')))->toBeTrue();
    });

    test('accepts a Bearer-prefixed secret', function (): void {
        expect((new HalaxySignatureValidator)->isValid(requestWithHeader('Bearer s3cret'), validatorConfig('s3cret')))->toBeTrue();
    });

    test('rejects a wrong secret', function (): void {
        expect((new HalaxySignatureValidator)->isValid(requestWithHeader('nope'), validatorConfig('s3cret')))->toBeFalse();
    });

    test('rejects a missing header', function (): void {
        expect((new HalaxySignatureValidator)->isValid(requestWithHeader(null), validatorConfig('s3cret')))->toBeFalse();
    });

    test('rejects requests when no secret is configured', function (): void {
        expect((new HalaxySignatureValidator)->isValid(requestWithHeader(null), validatorConfig('')))->toBeFalse();
    });

    test('rejects requests when the configured secret contains only whitespace', function (string $secret): void {
        expect((new HalaxySignatureValidator)->isValid(requestWithHeader($secret), validatorConfig($secret)))->toBeFalse();
    })->with([' ', "\t"]);
});
