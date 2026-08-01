# Halaxy Webhooks (Tenant-Aware, Path-Per-Event) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix Halaxy webhook event detection (action from endpoint path, not payload) and make wiring host-controllable for multi-tenant apps, per `docs/superpowers/specs/2026-07-31-halaxy-webhooks-design.md`.

**Architecture:** The package registers nine spatie `webhook-client` configs (`halaxy`, `halaxy-patient-created`, … `halaxy-invoice-deleted`) whose component classes come from `halaxy.webhooks` config. A `HalaxyWebhookRoutes::register()` helper registers one `Route::webhooks()` per config; the provider auto-calls it unless `register_routes` is off, so multi-tenant hosts can call it inside their own tenant-prefixed route group instead. `ProcessHalaxyWebhookJob` maps the stored `WebhookCall->name` to the typed event — no payload parsing for the action.

**Tech Stack:** PHP 8.4, Laravel 12/13, `spatie/laravel-webhook-client` ^3.4, Orchestra Testbench, Pest 5.

## Global Constraints

- Every PHP file: `declare(strict_types=1);`, `final` classes (matches all existing `src/` files).
- Follow existing code style: constructor property promotion, readonly props, docblocks with `@param array<string, mixed>` style generics.
- Tests are Pest 5. Feature tests use `Clinically\Halaxy\Tests\TestCase` (bound via `uses()` in `tests/Pest.php`). `QUEUE_CONNECTION=sync` in phpunit.xml, so spatie's queued job runs inline during feature tests.
- Run from package root: `vendor/bin/pest --no-coverage --exclude-testsuite=Integration` (alias `composer test`). Never run the Integration suite (hits live API).
- Before the final commit of the last task: `composer analyse` (PHPStan) and `composer lint` (Pint) must pass.
- Do not change constructor signatures of existing event classes (`PatientCreated`, `PatientUpdated`, `AppointmentCreated`, `AppointmentUpdated`, `AppointmentDeleted`, `InvoiceCreated`, `InvoiceUpdated`, `InvoiceDeleted`, `HalaxyWebhookReceived`).
- `Route::webhooks(...)` is a spatie macro invisible to PHPStan — keep the existing `/** @phpstan-ignore-next-line */` pattern where it's called.

---

### Task 1: Webhook fixture + `HalaxyWebhookRoutes` helper + provider route registration

**Files:**
- Create: `tests/Fixtures/webhook-patient-created.json`
- Create: `src/Webhooks/HalaxyWebhookRoutes.php`
- Modify: `src/HalaxyServiceProvider.php` (replace `registerWebhookRoutes()`)
- Modify: `config/halaxy.php` (add `register_routes` to the `webhooks` block)
- Test: `tests/Feature/Webhooks/WebhookRoutesTest.php`

**Interfaces:**
- Produces: `HalaxyWebhookRoutes::register(?string $prefix = null): void`; `HalaxyWebhookRoutes::EVENTS` (list of 8 kebab-case event slugs); `HalaxyWebhookRoutes::configNames(): array` returning `['halaxy', 'halaxy-patient-created', ...]` (9 names). Task 2 consumes `configNames()`; Task 3 consumes the `halaxy-*` config names; Task 4 consumes the routes and fixture.
- Consumes: spatie's `Route::webhooks($url, $name)` macro (names the route `webhook-client-{$name}`).

- [ ] **Step 1: Create a synthetic payload fixture** matching Halaxy's documented FHIR structure:

```json
{
  "timestamp": "2026-01-02T03:04:05+00:00",
  "type": "history",
  "link": [],
  "entry": [
    {
      "fullUrl": "urn:uuid:00000000-0000-4000-8000-000000000004",
      "resource": {
        "subscription": {
          "reference": "https://au-api.halaxy.com/main/Subscription/00000000-0000-4000-8000-000000000001",
          "type": "Subscription"
        },
        "topic": {
          "reference": "https://au-api.halaxy.com/main/SubscriptionTopic/00000000-0000-4000-8000-000000000002",
          "type": "SubscriptionTopic"
        },
        "status": "active",
        "type": "event-notification",
        "eventsSinceSubscriptionStart": "2",
        "notificationEvent": [
          {
            "eventNumber": "3",
            "timestamp": "2026-01-02T03:04:05+00:00",
            "focus": {
              "reference": "https://au-api.halaxy.com/main/Patient/synthetic-patient-001",
              "type": "Patient"
            },
            "id": "00000000-0000-4000-8000-000000000003"
          }
        ],
        "id": "00000000-0000-4000-8000-000000000004",
        "meta": {
          "profile": "http://hl7.org/fhir/uv/subscriptions-backport/StructureDefinition/backport-subscription-status"
        },
        "resourceType": "SubscriptionStatus"
      }
    }
  ],
  "contained": [],
  "extension": [],
  "modifierExtension": [],
  "id": "00000000-0000-4000-8000-000000000005",
  "meta": {
    "profile": "http://hl7.org/fhir/uv/subscriptions-backport/StructureDefinition/backport-subscription-notification",
    "security": [],
    "tag": []
  },
  "resourceType": "Bundle"
}
```

- [ ] **Step 2: Write the failing tests** — `tests/Feature/Webhooks/WebhookRoutesTest.php`:

```php
<?php

declare(strict_types=1);

use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;
use Illuminate\Support\Facades\Route;

describe('Webhook Routes', function (): void {
    test('auto-registers the base route and one route per event', function (): void {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

        expect($routes)->toHaveCount(9);

        $uris = $routes->map(fn ($route): string => $route->uri())->values()->all();

        expect($uris)->toContain('webhooks/halaxy')
            ->toContain('webhooks/halaxy/patient-created')
            ->toContain('webhooks/halaxy/patient-updated')
            ->toContain('webhooks/halaxy/appointment-created')
            ->toContain('webhooks/halaxy/appointment-updated')
            ->toContain('webhooks/halaxy/appointment-deleted')
            ->toContain('webhooks/halaxy/invoice-created')
            ->toContain('webhooks/halaxy/invoice-updated')
            ->toContain('webhooks/halaxy/invoice-deleted');
    });

    test('routes are named after their spatie config', function (): void {
        expect(Route::getRoutes()->getByName('webhook-client-halaxy'))->not->toBeNull();
        expect(Route::getRoutes()->getByName('webhook-client-halaxy-patient-created'))->not->toBeNull();
        expect(Route::getRoutes()->getByName('webhook-client-halaxy-invoice-deleted'))->not->toBeNull();
    });

    test('register() inside a group inherits its prefix', function (): void {
        Route::prefix('app/{tenant}/webhooks')->group(function (): void {
            HalaxyWebhookRoutes::register('halaxy');
        });

        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri());

        expect($uris)->toContain('app/{tenant}/webhooks/halaxy/appointment-created')
            ->toContain('app/{tenant}/webhooks/halaxy');
    });

    test('configNames returns the base name plus one per event', function (): void {
        expect(HalaxyWebhookRoutes::configNames())->toBe([
            'halaxy',
            'halaxy-patient-created',
            'halaxy-patient-updated',
            'halaxy-appointment-created',
            'halaxy-appointment-updated',
            'halaxy-appointment-deleted',
            'halaxy-invoice-created',
            'halaxy-invoice-updated',
            'halaxy-invoice-deleted',
        ]);
    });
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Webhooks/WebhookRoutesTest.php --no-coverage`
Expected: FAIL — `HalaxyWebhookRoutes` class not found.

- [ ] **Step 4: Create `src/Webhooks/HalaxyWebhookRoutes.php`**

```php
<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Webhooks;

use Illuminate\Support\Facades\Route;

/**
 * Registers the Halaxy webhook endpoints.
 *
 * Halaxy payloads do not identify the action (created/updated/deleted), so
 * each event type gets its own endpoint path; the Halaxy UI webhook for that
 * event must point at the matching URL. Multi-tenant hosts disable auto
 * registration (HALAXY_WEBHOOK_ROUTES=false) and call register() inside
 * their own tenant-prefixed, tenant-middleware route group.
 */
final class HalaxyWebhookRoutes
{
    /**
     * Event slugs, each also the path suffix under the base path.
     */
    public const array EVENTS = [
        'patient-created',
        'patient-updated',
        'appointment-created',
        'appointment-updated',
        'appointment-deleted',
        'invoice-created',
        'invoice-updated',
        'invoice-deleted',
    ];

    /**
     * Register the base (generic) route and one route per event.
     *
     * @param  string|null  $prefix  Path prefix relative to the current route
     *                               group. Defaults to halaxy.webhooks.path.
     */
    public static function register(?string $prefix = null): void
    {
        $prefix = rtrim($prefix ?? config('halaxy.webhooks.path', 'webhooks/halaxy'), '/');

        /** @phpstan-ignore-next-line */
        Route::webhooks($prefix, 'halaxy');

        foreach (self::EVENTS as $event) {
            /** @phpstan-ignore-next-line */
            Route::webhooks("{$prefix}/{$event}", "halaxy-{$event}");
        }
    }

    /**
     * All spatie webhook-client config names the package registers.
     *
     * @return array<int, string>
     */
    public static function configNames(): array
    {
        return ['halaxy', ...array_map(
            static fn (string $event): string => "halaxy-{$event}",
            self::EVENTS,
        )];
    }
}
```

- [ ] **Step 5: Update the provider and config**

In `src/HalaxyServiceProvider.php`, replace the body of `registerWebhookRoutes()` (currently a single `Route::webhooks($path, 'halaxy')` call at the bottom of the class):

```php
    /**
     * Register webhook routes (unless the host registers them itself).
     */
    private function registerWebhookRoutes(): void
    {
        if (! config('halaxy.webhooks.enabled', false)) {
            return;
        }

        if (! config('halaxy.webhooks.register_routes', true)) {
            return;
        }

        HalaxyWebhookRoutes::register();
    }
```

Add the import `use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;` and remove the now-unused `use Illuminate\Support\Facades\Route;` if nothing else references it.

In `config/halaxy.php`, inside the `webhooks` block, directly under `'enabled' => ...`, add:

```php
        // Auto-register webhook routes at the path below. Multi-tenant hosts
        // set this to false and call HalaxyWebhookRoutes::register() inside
        // their own tenant-prefixed route group.
        'register_routes' => env('HALAXY_WEBHOOK_ROUTES', true),
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/pest tests/Feature/Webhooks/WebhookRoutesTest.php --no-coverage`
Expected: PASS (4 tests).

- [ ] **Step 7: Run the whole suite to catch regressions**

Run: `composer test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Webhooks/HalaxyWebhookRoutes.php src/HalaxyServiceProvider.php config/halaxy.php tests/Feature/Webhooks/WebhookRoutesTest.php tests/Fixtures/webhook-patient-created.json
git commit -m "feat: path-per-event webhook routes with host-controllable registration"
```

---

### Task 2: Boot-time config toggle tests (`register_routes=false`, `enabled=false`)

**Files:**
- Create: `tests/WebhookBootConfigTestCase.php`, `tests/WebhooksDisabledTestCase.php`
- Test: `tests/Feature/Webhooks/BootConfig/RoutesDisabledTest.php`, `tests/Feature/Webhooks/Disabled/WebhooksDisabledTest.php`
- Modify: `tests/Pest.php`

**Interfaces:**
- Consumes: `halaxy.webhooks.register_routes` config key (Task 1). Also asserts the config-override keys added in Task 3, so this file is touched again there.

`register_routes` is read at provider boot, before a Pest test body runs, so this needs a TestCase subclass with its own `defineEnvironment()` bound to a dedicated subdirectory.

- [ ] **Step 1: Create `tests/WebhookBootConfigTestCase.php`**

```php
<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Tests;

abstract class WebhookBootConfigTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('halaxy.webhooks.register_routes', false);
    }
}
```

- [ ] **Step 2: Create `tests/WebhooksDisabledTestCase.php`**

```php
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
```

- [ ] **Step 3: Bind both in `tests/Pest.php`** — add after the existing `uses(TestCase::class)->in('Feature');` line:

```php
uses(WebhookBootConfigTestCase::class)->in('Feature/Webhooks/BootConfig');
uses(WebhooksDisabledTestCase::class)->in('Feature/Webhooks/Disabled');
```

with imports `use Clinically\Halaxy\Tests\WebhookBootConfigTestCase;` and `use Clinically\Halaxy\Tests\WebhooksDisabledTestCase;`.

- [ ] **Step 4: Write the tests** — `tests/Feature/Webhooks/BootConfig/RoutesDisabledTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('no webhook routes are registered when register_routes is false', function (): void {
    $webhookRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

    expect($webhookRoutes)->toHaveCount(0);
});
```

And `tests/Feature/Webhooks/Disabled/WebhooksDisabledTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

test('disabling webhooks registers neither routes nor spatie configs', function (): void {
    $webhookRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'webhooks/halaxy'));

    $halaxyConfigs = collect(config('webhook-client.configs', []))
        ->filter(fn (array $config): bool => str_starts_with($config['name'], 'halaxy'));

    expect($webhookRoutes)->toHaveCount(0)
        ->and($halaxyConfigs)->toHaveCount(0);
});
```

- [ ] **Step 5: Run them**

Run: `vendor/bin/pest tests/Feature/Webhooks --no-coverage`
Expected: PASS. If Pest complains about conflicting `uses()` bindings for the subdirectories, move them out of `Feature/` to top-level `tests/WebhookBootConfig/` and `tests/WebhooksDisabled/`, add each as a `<testsuite>` directory in `phpunit.xml`, and bind the `uses()` lines to those paths instead — then re-run.

- [ ] **Step 6: Commit**

```bash
git add tests
git commit -m "test: cover webhook boot-time config toggles"
```

---

### Task 3: Configurable spatie config entries; remove `HalaxyWebhookProfile`

**Files:**
- Modify: `src/HalaxyServiceProvider.php` (`registerWebhookConfig()`, lines ~130-160)
- Modify: `config/halaxy.php` (`webhooks` block: add class overrides, remove `events`)
- Delete: `src/Webhooks/HalaxyWebhookProfile.php`
- Modify: `tests/WebhookBootConfigTestCase.php`
- Test: `tests/Feature/Webhooks/WebhookConfigTest.php`, `tests/Feature/Webhooks/BootConfig/ConfigOverridesTest.php`

**Interfaces:**
- Consumes: `HalaxyWebhookRoutes::configNames()` (Task 1).
- Produces: nine `webhook-client.configs` entries named per `configNames()`, each with `signature_validator`, `webhook_profile`, `process_webhook_job`, `webhook_model` taken from `halaxy.webhooks.*` config (defaults: `HalaxySignatureValidator`, spatie `ProcessEverythingWebhookProfile`, `ProcessHalaxyWebhookJob`, spatie `WebhookCall`). Tasks 4-5 rely on these entries existing.

- [ ] **Step 1: Write the failing tests** — `tests/Feature/Webhooks/WebhookConfigTest.php`:

```php
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
```

And `tests/Feature/Webhooks/BootConfig/ConfigOverridesTest.php` (runs under `WebhookBootConfigTestCase` from Task 2):

```php
<?php

declare(strict_types=1);

use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;

test('host-configured classes override the package defaults', function (): void {
    $config = collect(config('webhook-client.configs'))
        ->firstWhere('name', 'halaxy-patient-created');

    expect($config['signature_validator'])->toBe(DefaultSignatureValidator::class);
});
```

- [ ] **Step 2: Extend `tests/WebhookBootConfigTestCase.php`** — add to `defineEnvironment()` after the `register_routes` line:

```php
        $app['config']->set('halaxy.webhooks.signature_validator', \Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator::class);
```

- [ ] **Step 3: Run tests to verify the right failures**

Run: `vendor/bin/pest tests/Feature/Webhooks --no-coverage`
Expected: `WebhookConfigTest` FAILS (only the single old `halaxy` entry exists, and it references `HalaxyWebhookProfile`); `ConfigOverridesTest` FAILS.

- [ ] **Step 4: Rewrite `registerWebhookConfig()` in the provider**

```php
    /**
     * Merge one webhook-client config per Halaxy endpoint.
     */
    private function registerWebhookConfig(): void
    {
        if (! config('halaxy.webhooks.enabled', false)) {
            return;
        }

        if (method_exists($this->app, 'providerIsLoaded') && ! $this->app->providerIsLoaded(WebhookClientServiceProvider::class)) {
            $this->app->register(WebhookClientServiceProvider::class);
        }

        $this->app->booted(function (): void {
            $webhooks = config('halaxy.webhooks');

            $halaxyConfigs = array_map(static fn (string $name): array => [
                'name' => $name,
                'signing_secret' => $webhooks['signing_secret'] ?? null,
                'signature_header_name' => $webhooks['signature_header_name'] ?? 'Authorization',
                'signature_validator' => $webhooks['signature_validator'] ?? HalaxySignatureValidator::class,
                'webhook_profile' => $webhooks['webhook_profile'] ?? ProcessEverythingWebhookProfile::class,
                'webhook_response' => DefaultRespondsTo::class,
                'webhook_model' => $webhooks['webhook_model'] ?? WebhookCall::class,
                'store_headers' => ['*'],
                'process_webhook_job' => $webhooks['process_webhook_job'] ?? ProcessHalaxyWebhookJob::class,
            ], HalaxyWebhookRoutes::configNames());

            config(['webhook-client.configs' => [
                ...config('webhook-client.configs', []),
                ...$halaxyConfigs,
            ]]);
        });
    }
```

Read `config('halaxy.webhooks')` *inside* the booted callback (as shown, first line) so late config changes made before boot completes are honored. Update imports: add `use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;`, remove `use Clinically\Halaxy\Webhooks\HalaxyWebhookProfile;`.

- [ ] **Step 5: Update the config file**

In `config/halaxy.php` `webhooks` block: **remove** the `'events' => null,` entry and its comment lines; **add** after `signature_header_name`:

```php
        // Component classes for the spatie webhook-client entries. Override
        // these in a host app to substitute tenant-aware implementations
        // (e.g. a validator that reads the tenant's own secret).
        'signature_validator' => \Clinically\Halaxy\Webhooks\HalaxySignatureValidator::class,
        'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
        'process_webhook_job' => \Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob::class,
        'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
```

- [ ] **Step 6: Delete the profile**

```bash
git rm src/Webhooks/HalaxyWebhookProfile.php
```

(`ProcessHalaxyWebhookJob` does not reference it; verify with `grep -rn HalaxyWebhookProfile src tests`.)

- [ ] **Step 7: Run the tests**

Run: `vendor/bin/pest tests/Feature/Webhooks --no-coverage` then `composer test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A src config tests
git commit -m "feat: per-endpoint spatie webhook configs with host-overridable classes"
```

---

### Task 4: Rewrite `ProcessHalaxyWebhookJob` (config name → typed event)

**Files:**
- Modify: `src/Webhooks/ProcessHalaxyWebhookJob.php`
- Test: `tests/Feature/Webhooks/ProcessHalaxyWebhookJobTest.php` (Feature, not Unit — `event()` needs the Orchestra app's dispatcher)

**Interfaces:**
- Consumes: `WebhookCall->name` values produced by the Task 3 configs (`halaxy`, `halaxy-patient-created`, …); typed event constructors `(string $resourceReference, ?string $timestamp, WebhookCall $webhookCall)` — unchanged.
- Produces: `handle()` dispatching exactly one event per call. Task 5's feature tests rely on this behavior end-to-end.

- [ ] **Step 1: Write the failing tests** — `tests/Feature/Webhooks/ProcessHalaxyWebhookJobTest.php` (the job is exercised directly with an unsaved `WebhookCall`; the Feature TestCase provides the event dispatcher for `Event::fake()`):

```php
<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\Appointment\AppointmentCreated;
use Clinically\Halaxy\Events\Appointment\AppointmentDeleted;
use Clinically\Halaxy\Events\Appointment\AppointmentUpdated;
use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Clinically\Halaxy\Events\Invoice\InvoiceCreated;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Invoice\InvoiceUpdated;
use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Events\Patient\PatientUpdated;
use Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

function makeWebhookCall(string $name): WebhookCall
{
    $payload = json_decode(
        file_get_contents(__DIR__.'/../../Fixtures/webhook-patient-created.json'),
        true,
    );

    return new WebhookCall(['name' => $name, 'payload' => $payload]);
}

describe('ProcessHalaxyWebhookJob', function (): void {
    test('dispatches the typed event matching the config name', function (string $name, string $eventClass): void {
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall($name)))->handle();

        Event::assertDispatched($eventClass, function (object $event): bool {
            return $event->resourceReference === 'https://au-api.halaxy.com/main/Patient/synthetic-patient-001'
                && $event->timestamp === '2026-01-02T03:04:05+00:00';
        });
    })->with([
        ['halaxy-patient-created', PatientCreated::class],
        ['halaxy-patient-updated', PatientUpdated::class],
        ['halaxy-appointment-created', AppointmentCreated::class],
        ['halaxy-appointment-updated', AppointmentUpdated::class],
        ['halaxy-appointment-deleted', AppointmentDeleted::class],
        ['halaxy-invoice-created', InvoiceCreated::class],
        ['halaxy-invoice-updated', InvoiceUpdated::class],
        ['halaxy-invoice-deleted', InvoiceDeleted::class],
    ]);

    test('generic config name dispatches HalaxyWebhookReceived with resource type from focus', function (): void {
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy')))->handle();

        Event::assertDispatched(HalaxyWebhookReceived::class, function (HalaxyWebhookReceived $event): bool {
            return $event->eventType === 'patient.unknown'
                && $event->resourceId() === 'synthetic-patient-001'
                && $event->resourceType() === 'Patient';
        });
    });

    test('unrecognised config name falls back to HalaxyWebhookReceived', function (): void {
        Event::fake();

        (new ProcessHalaxyWebhookJob(makeWebhookCall('halaxy-something-new')))->handle();

        Event::assertDispatched(HalaxyWebhookReceived::class);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Webhooks/ProcessHalaxyWebhookJobTest.php --no-coverage`
Expected: FAIL — the current job derives `patient.unknown` from topic parsing and dispatches `HalaxyWebhookReceived` for every name.

- [ ] **Step 3: Rewrite `src/Webhooks/ProcessHalaxyWebhookJob.php`**

```php
<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Webhooks;

use Clinically\Halaxy\Events\Appointment\AppointmentCreated;
use Clinically\Halaxy\Events\Appointment\AppointmentDeleted;
use Clinically\Halaxy\Events\Appointment\AppointmentUpdated;
use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Clinically\Halaxy\Events\Invoice\InvoiceCreated;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Invoice\InvoiceUpdated;
use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Events\Patient\PatientUpdated;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

final class ProcessHalaxyWebhookJob extends ProcessWebhookJob
{
    /**
     * Halaxy payloads don't identify the action, so the event type is encoded
     * in the endpoint path — and therefore in the webhook config name stored
     * on the WebhookCall.
     *
     * @var array<string, class-string>
     */
    private const array EVENT_MAP = [
        'halaxy-patient-created' => PatientCreated::class,
        'halaxy-patient-updated' => PatientUpdated::class,
        'halaxy-appointment-created' => AppointmentCreated::class,
        'halaxy-appointment-updated' => AppointmentUpdated::class,
        'halaxy-appointment-deleted' => AppointmentDeleted::class,
        'halaxy-invoice-created' => InvoiceCreated::class,
        'halaxy-invoice-updated' => InvoiceUpdated::class,
        'halaxy-invoice-deleted' => InvoiceDeleted::class,
    ];

    /**
     * Process the webhook.
     */
    public function handle(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->webhookCall->payload ?? [];

        $resourceReference = $this->extractResourceReference($payload);
        $timestamp = $this->extractTimestamp($payload);

        $eventClass = self::EVENT_MAP[$this->webhookCall->name] ?? null;

        if ($eventClass !== null) {
            event(new $eventClass($resourceReference, $timestamp, $this->webhookCall));

            return;
        }

        event(new HalaxyWebhookReceived(
            $this->determineEventType($payload),
            $resourceReference,
            $timestamp,
            $this->webhookCall,
        ));
    }

    /**
     * Best-effort event type for webhooks on the generic endpoint: the payload
     * identifies the resource (focus.type) but never the action.
     *
     * @param  array<string, mixed>  $payload
     */
    private function determineEventType(array $payload): string
    {
        $focus = $payload['entry'][0]['resource']['notificationEvent'][0]['focus'] ?? [];

        $resourceType = strtolower($focus['type'] ?? 'unknown');

        return "{$resourceType}.unknown";
    }

    /**
     * Extract the resource reference from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractResourceReference(array $payload): string
    {
        $focus = $payload['entry'][0]['resource']['notificationEvent'][0]['focus'] ?? [];

        return $focus['reference'] ?? '';
    }

    /**
     * Extract the timestamp from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractTimestamp(array $payload): ?string
    {
        return $payload['timestamp']
            ?? $payload['entry'][0]['resource']['notificationEvent'][0]['timestamp']
            ?? null;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/pest tests/Feature/Webhooks/ProcessHalaxyWebhookJobTest.php --no-coverage` then `composer test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Webhooks/ProcessHalaxyWebhookJob.php tests/Feature/Webhooks/ProcessHalaxyWebhookJobTest.php
git commit -m "fix: derive webhook event type from endpoint config name, not payload"
```

---

### Task 5: End-to-end pipeline feature tests + validator unit tests

**Files:**
- Modify: `tests/TestCase.php` (add `migrateWebhookCallsTable()` helper and a test signing secret)
- Test: `tests/Feature/Webhooks/WebhookPipelineTest.php`, `tests/Unit/Webhooks/HalaxySignatureValidatorTest.php`

**Interfaces:**
- Consumes: routes (Task 1), configs (Task 3), job behavior (Task 4), fixture (Task 1).

- [ ] **Step 1: Add helpers to `tests/TestCase.php`**

In `defineEnvironment()`, add:

```php
        $app['config']->set('halaxy.webhooks.signing_secret', 'test-webhook-secret');
```

Add a method:

```php
    /**
     * Create the spatie webhook_calls table for pipeline tests.
     */
    protected function migrateWebhookCallsTable(): void
    {
        foreach ([
            'create_webhook_calls_table',
            'add_attachments_to_webhook_calls_table',
        ] as $stub) {
            $migration = include dirname(__DIR__)."/vendor/spatie/laravel-webhook-client/database/migrations/{$stub}.php.stub";
            $migration->up();
        }
    }
```

- [ ] **Step 2: Write the validator unit tests** — `tests/Unit/Webhooks/HalaxySignatureValidatorTest.php`. `WebhookConfig` requires resolvable classes but no app, and `isValid()` only reads `signingSecret`/`signatureHeaderName`:

```php
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
        expect((new HalaxySignatureValidator())->isValid(requestWithHeader('s3cret'), validatorConfig('s3cret')))->toBeTrue();
    });

    test('accepts a Bearer-prefixed secret', function (): void {
        expect((new HalaxySignatureValidator())->isValid(requestWithHeader('Bearer s3cret'), validatorConfig('s3cret')))->toBeTrue();
    });

    test('rejects a wrong secret', function (): void {
        expect((new HalaxySignatureValidator())->isValid(requestWithHeader('nope'), validatorConfig('s3cret')))->toBeFalse();
    });

    test('rejects a missing header', function (): void {
        expect((new HalaxySignatureValidator())->isValid(requestWithHeader(null), validatorConfig('s3cret')))->toBeFalse();
    });

    test('rejects requests when no secret is configured', function (): void {
        expect((new HalaxySignatureValidator())->isValid(requestWithHeader(null), validatorConfig('')))->toBeFalse();
    });
});
```

- [ ] **Step 3: Write the pipeline feature tests** — `tests/Feature/Webhooks/WebhookPipelineTest.php`:

```php
<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\WebhookClient\Models\WebhookCall;

beforeEach(function (): void {
    $this->migrateWebhookCallsTable();
    $this->payload = json_decode($this->fixture('webhook-patient-created.json'), true);
});

describe('Webhook Pipeline', function (): void {
    test('event endpoint stores the call and dispatches the typed event', function (): void {
        Event::fake([PatientCreated::class]);

        $this->postJson('/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        expect(WebhookCall::query()->count())->toBe(1)
            ->and(WebhookCall::query()->first()->name)->toBe('halaxy-patient-created');

        Event::assertDispatched(PatientCreated::class, function (PatientCreated $event): bool {
            return $event->resourceReference === 'https://au-api.halaxy.com/main/Patient/synthetic-patient-001'
                && $event->timestamp === '2026-01-02T03:04:05+00:00'
                && $event->patientId() === 'synthetic-patient-001';
        });
    });

    test('the action comes from the URL even for another resource endpoint', function (): void {
        Event::fake([InvoiceDeleted::class]);

        $this->postJson('/webhooks/halaxy/invoice-deleted', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(InvoiceDeleted::class);
    });

    test('generic endpoint dispatches HalaxyWebhookReceived', function (): void {
        Event::fake([HalaxyWebhookReceived::class]);

        $this->postJson('/webhooks/halaxy', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(HalaxyWebhookReceived::class, function (HalaxyWebhookReceived $event): bool {
            return $event->eventType === 'patient.unknown'
                && $event->resourceType() === 'Patient'
                && $event->resourceId() === 'synthetic-patient-001';
        });
    });

    test('Bearer-prefixed secret is accepted', function (): void {
        Event::fake([PatientCreated::class]);

        $this->postJson('/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'Bearer test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(PatientCreated::class);
    });

    test('wrong secret is rejected and nothing is stored or dispatched', function (): void {
        Event::fake();

        $response = $this->postJson('/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'wrong-secret',
        ]);

        expect($response->status())->toBeGreaterThanOrEqual(400)
            ->and(WebhookCall::query()->count())->toBe(0);

        Event::assertNotDispatched(PatientCreated::class);
    });

    test('tenant-style group registration processes webhooks end to end', function (): void {
        Event::fake([PatientCreated::class]);

        Route::prefix('app/{tenant}/webhooks')->group(function (): void {
            HalaxyWebhookRoutes::register('halaxy');
        });

        $this->postJson('/app/acme/webhooks/halaxy/patient-created', $this->payload, [
            'Authorization' => 'test-webhook-secret',
        ])->assertSuccessful();

        Event::assertDispatched(PatientCreated::class);
    });
});
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/pest tests/Feature/Webhooks tests/Unit/Webhooks --no-coverage`
Expected: PASS. Spatie surfaces an invalid signature as a `WebhookFailed` exception, which the default test exception handler renders as a 500 — that still satisfies `toBeGreaterThanOrEqual(400)`; do not call `withoutExceptionHandling()` in that test.

- [ ] **Step 5: Run the full suite**

Run: `composer test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/TestCase.php tests/Feature/Webhooks/WebhookPipelineTest.php tests/Unit/Webhooks/HalaxySignatureValidatorTest.php
git commit -m "test: cover webhook pipeline end to end and signature validator"
```

---

### Task 6: Docs, changelog, static analysis, format

**Files:**
- Modify: `README.md` (Webhooks section, ~line 252)
- Modify: `CHANGELOG.md`
- Modify: `IMPLEMENTATION-PLAN.md` is historical — leave it alone.

- [ ] **Step 1: Rewrite the README "Webhooks" section**

Replace the existing section content with (keep the `## Webhooks` heading level consistent with the file):

````markdown
## Webhooks

The SDK integrates `spatie/laravel-webhook-client` to receive Halaxy webhooks.

Halaxy webhook payloads identify *which resource* changed but not *what
happened* to it, so each event type gets its own endpoint. When creating each
webhook in Halaxy (Settings > Integrations > Webhooks), point it at the URL
matching its event:

| Halaxy event        | Endpoint                                    |
| ------------------- | ------------------------------------------- |
| Patient Create      | `https://your-app/webhooks/halaxy/patient-created` |
| Patient Update      | `https://your-app/webhooks/halaxy/patient-updated` |
| Appointment Create  | `https://your-app/webhooks/halaxy/appointment-created` |
| Appointment Update  | `https://your-app/webhooks/halaxy/appointment-updated` |
| Appointment Delete  | `https://your-app/webhooks/halaxy/appointment-deleted` |
| Invoice Create      | `https://your-app/webhooks/halaxy/invoice-created` |
| Invoice Update      | `https://your-app/webhooks/halaxy/invoice-updated` |
| Invoice Delete      | `https://your-app/webhooks/halaxy/invoice-deleted` |

Set the webhook's "Authentication Header" in Halaxy to your
`HALAXY_WEBHOOK_SECRET` value.

```env
HALAXY_WEBHOOKS_ENABLED=true
HALAXY_WEBHOOK_SECRET=your-webhook-secret
HALAXY_WEBHOOK_PATH=webhooks/halaxy
```

Exclude the webhook routes from CSRF verification in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['webhooks/halaxy', 'webhooks/halaxy/*']);
})
```

Run the spatie migration to create the `webhook_calls` table:

```bash
php artisan vendor:publish --provider="Spatie\WebhookClient\WebhookClientServiceProvider" --tag="webhook-client-migrations"
php artisan migrate
```

Each endpoint dispatches its typed event — `PatientCreated`, `PatientUpdated`,
`AppointmentCreated`, `AppointmentUpdated`, `AppointmentDeleted`,
`InvoiceCreated`, `InvoiceUpdated`, `InvoiceDeleted` — exposing
`resourceReference`, `timestamp`, `webhookCall`, and an ID accessor
(e.g. `patientId()`). Requests to the base path dispatch the generic
`HalaxyWebhookReceived`.

### Multi-tenant hosts

Disable auto-registration and register the routes inside your own
tenant-scoped group:

```env
HALAXY_WEBHOOK_ROUTES=false
```

```php
// e.g. routes/tenant-webhooks.php, grouped under {tenant} + tenant middleware
use Clinically\Halaxy\Webhooks\HalaxyWebhookRoutes;

HalaxyWebhookRoutes::register('halaxy');
```

Point each Halaxy webhook at the tenant URL, e.g.
`https://your-app/app/{tenant}/webhooks/halaxy/appointment-created`.

For per-tenant secrets, substitute your own validator (and any other
component) in `config/halaxy.php`:

```php
'webhooks' => [
    // ...
    'signature_validator' => App\Webhooks\TenantHalaxySignatureValidator::class,
],
```
````

Also update the feature bullet near line 11 if it mentions the removed profile/events filter.

- [ ] **Step 2: Add a CHANGELOG entry** under an "Unreleased" heading (create one at the top if absent), matching the file's existing style:

```markdown
## [Unreleased]

### Changed
- **Breaking:** Webhook event detection now uses one endpoint per event type
  (`webhooks/halaxy/{event}`); Halaxy payloads don't identify the action, so
  the previous topic-URL parsing never matched and every webhook fell through
  to `HalaxyWebhookReceived`. Point each Halaxy webhook at its event's URL.
- Webhook component classes (`signature_validator`, `webhook_profile`,
  `process_webhook_job`, `webhook_model`) are configurable for tenant-aware
  hosts; route auto-registration can be disabled via `HALAXY_WEBHOOK_ROUTES`
  and replaced with `HalaxyWebhookRoutes::register()` in a tenant route group.

### Removed
- `HalaxyWebhookProfile` and the `halaxy.webhooks.events` config filter —
  select events by registering only the URLs you need in Halaxy.
```

- [ ] **Step 3: Static analysis and formatting**

Run: `composer analyse` — fix any reported issues (likely candidates: the `@phpstan-ignore-next-line` placement on `Route::webhooks`, array shape of `config('halaxy.webhooks')`).
Run: `composer lint` (Pint auto-fixes).
Run: `composer test`
Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs: webhook setup for standalone and multi-tenant hosts"
```
