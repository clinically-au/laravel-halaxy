<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Tests;

use Clinically\Halaxy\HalaxyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class WebhooksDefaultTestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            HalaxyServiceProvider::class,
        ];
    }
}
