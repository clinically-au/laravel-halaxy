<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Tests;

use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;

abstract class WebhookBootConfigTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('halaxy.webhooks.register_routes', false);
        $app['config']->set('halaxy.webhooks.signature_validator', DefaultSignatureValidator::class);
    }
}
