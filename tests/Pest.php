<?php

declare(strict_types=1);

use Clinically\Halaxy\Tests\IntegrationTestCase;
use Clinically\Halaxy\Tests\TestCase;
use Clinically\Halaxy\Tests\WebhookBootConfigTestCase;
use Clinically\Halaxy\Tests\WebhooksDefaultTestCase;
use Clinically\Halaxy\Tests\WebhooksDisabledTestCase;
use Clinically\Halaxy\Tests\WebhooksWithoutSecretTestCase;

uses(TestCase::class)->in('Feature');
uses(WebhookBootConfigTestCase::class)->in('WebhookBootConfig');
uses(WebhooksDefaultTestCase::class)->in('WebhooksDefault');
uses(WebhooksDisabledTestCase::class)->in('WebhooksDisabled');
uses(WebhooksWithoutSecretTestCase::class)->in('WebhooksWithoutSecret');
uses(IntegrationTestCase::class)->in('Integration');
uses()->in('Unit');
