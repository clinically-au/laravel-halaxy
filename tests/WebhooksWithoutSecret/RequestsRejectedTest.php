<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\Patient\PatientCreated;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

test('enabled webhooks reject requests when no signing secret is configured', function (): void {
    $this->migrateWebhookCallsTable();
    Event::fake([PatientCreated::class]);

    $response = $this->postJson(
        '/webhooks/halaxy/patient-created',
        $this->fixtureArray('webhook-patient-created.json'),
        ['Authorization' => 'attacker-controlled-value'],
    );

    expect($response->status())->toBeGreaterThanOrEqual(400)
        ->and(WebhookCall::query()->count())->toBe(0);

    Event::assertNotDispatched(PatientCreated::class);
});
