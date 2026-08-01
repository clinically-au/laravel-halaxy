<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Tests;

abstract class WebhooksDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('halaxy.webhooks.enabled', false);
    }
}
