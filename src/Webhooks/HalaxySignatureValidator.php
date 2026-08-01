<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

final class HalaxySignatureValidator implements SignatureValidator
{
    /**
     * Validate the webhook signature.
     *
     * Halaxy uses an optional "Authentication Header" for webhook validation,
     * which can be a Bearer token or custom value configured in Halaxy.
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $secret = $config->signingSecret;

        // Fail closed if webhook handling was enabled without a secret.
        if (trim($secret) === '') {
            return false;
        }

        $signature = $request->header($config->signatureHeaderName);

        if ($signature === null) {
            return false;
        }

        // Handle Bearer token format
        if (str_starts_with($signature, 'Bearer ')) {
            $signature = substr($signature, 7);
        }

        return hash_equals($secret, $signature);
    }
}
