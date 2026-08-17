# clinically/laravel-halaxy

A Laravel SDK for the [Halaxy](https://developers.halaxy.com) FHIR R4B healthcare API.

> [!IMPORTANT]
> This is an independent, unofficial project. It is not affiliated with,
> endorsed by, or supported by Halaxy. Halaxy is a trademark of its respective
> owner.

## Features

- Fluent resource API for every documented Halaxy endpoint
- FHIR query builder with pagination (`paginate()`, lazy `all()`)
- Multi-tenant support with per-tenant OAuth token caching
- AU/EU region switching
- Webhook handling via `spatie/laravel-webhook-client` with typed events
- Typed exceptions for authentication, validation, rate-limit, and server errors

## Requirements

- PHP 8.5+
- Laravel 13+

## Installation

```bash
composer require clinically/laravel-halaxy
```

Publish the configuration:

```bash
php artisan vendor:publish --tag="halaxy-config"
```

Set your credentials in `.env` (created per practice in Halaxy's developer settings):

```env
HALAXY_CLIENT_ID=your-client-id
HALAXY_CLIENT_SECRET=your-client-secret
HALAXY_REGION=au  # au or eu
```

## Usage

### Patients

```php
use Clinically\Halaxy\DTOs\ContactPoint;
use Clinically\Halaxy\DTOs\PatientPayload;
use Clinically\Halaxy\Facades\Halaxy;

// Find, list, create
$patient = Halaxy::patients()->find('123456')->json();
$bundle = Halaxy::patients()->list(['_count' => 30]);

$payload = new PatientPayload(
    name: [['use' => 'official', 'given' => ['John'], 'family' => 'Doe']],
    telecom: [
        ContactPoint::mobile('+61400000000'),
        ContactPoint::email('john@example.com'),
    ],
    birthDate: '1990-01-15',
);

$created = Halaxy::patients()->create($payload);

// Partial update (JSON merge-patch)
Halaxy::patients()->update('123456', $payload);

// Full replace (PUT) — destructive: writable properties omitted from the
// payload are REMOVED from the profile (only file attachments survive).
// Always send the complete desired state.
Halaxy::patients()->replace('123456', $payload);

// Raw arrays remain supported for backwards compatibility, but typed contact
// points enforce Halaxy's required SMS/mobile type and compact international
// phone format before a request is sent.

// Export references for every patient in the practice
$references = Halaxy::patients()->exportIds()->json();
```

### Query Builder

```php
$paginator = Halaxy::patients()
    ->query()
    ->where('family', 'Doe')
    ->where('_lastUpdated', 'gt', '2024-01-01')
    ->orderBy('family')
    ->paginate(50);

foreach ($paginator->items() as $patient) {
    // current page
}

foreach ($paginator->all() as $patient) {
    // lazily fetches every page
}
```

### Appointments

Booking uses Halaxy's `$book` operation, which expects a FHIR **Parameters**
resource (not a bare Appointment) and creates related resources (invoice,
clinical note) as part of the booking:

```php
// Find available times (start, end, and duration are required)
$available = Halaxy::appointments()->findAvailable([
    'start' => '2026-02-15T09:00:00+11:00',
    'end' => '2026-02-22T17:00:00+11:00',
    'duration' => '30',
    'show' => 'first-available',
]);

// Book
$booked = Halaxy::appointments()->book([
    'parameter' => [
        [
            'name' => 'appt-resource',
            'resource' => [
                'resourceType' => 'Appointment',
                'start' => '2026-02-15T09:00:00+11:00',
                'end' => '2026-02-15T09:30:00+11:00',
                'minutesDuration' => 30,
                'participant' => [
                    ['actor' => ['type' => 'PractitionerRole', 'reference' => 'https://au-api.halaxy.com/main/PractitionerRole/PR-123']],
                ],
            ],
        ],
        ['name' => 'patient-id', 'valueReference' => ['type' => 'Patient', 'reference' => 'https://au-api.halaxy.com/main/Patient/123456']],
        ['name' => 'healthcare-service-id', 'valueReference' => ['type' => 'HealthcareService', 'reference' => 'https://au-api.halaxy.com/main/HealthcareService/789']],
        ['name' => 'location-type', 'valueCode' => 'clinic'],
        ['name' => 'status', 'valueCode' => 'booked'],
    ],
]);

// Update (merge-patch): description, start/end, comment, participant, ...
Halaxy::appointments()->update($appointmentId, ['comment' => 'Running late']);
```

Appointments have no writable `status` field. Cancellation is done by
patching the patient participant's `modifierExtension`:

```php
Halaxy::appointments()->update($appointmentId, [
    'participant' => [[
        'actor' => ['type' => 'Patient', 'reference' => "https://au-api.halaxy.com/main/Patient/{$patientId}"],
        'modifierExtension' => [[
            'url' => 'https://terminology.halaxy.com/StructureDefinition/appointment-participant-status',
            'valueCoding' => [
                'system' => 'https://au-api.halaxy.com/presets/CodeSystem/appointment-participant-status',
                'code' => 'cancelled (no charge)',
                'display' => 'cancelled (no charge)',
            ],
        ]],
    ]],
]);
```

### Referrals

Halaxy referrals use Halaxy-specific property names rather than the generic
FHIR ServiceRequest names. A Coverage record must already exist. Use the typed
payload to produce `coverage`, `created`, `active`, `comment`, and `attachments`:

```php
use Clinically\Halaxy\DTOs\ReferralAttachment;
use Clinically\Halaxy\DTOs\ReferralPayload;

$referral = Halaxy::referrals()->create(new ReferralPayload(
    coverageReference: "Coverage/{$coverageId}",
    subjectReference: "Patient/{$patientId}",
    requesterReference: "PractitionerRole/{$requesterId}",
    created: new DateTimeImmutable('now'),
    comment: 'Specialist review requested',
    attachments: [
        new ReferralAttachment('application/pdf', $base64Data, 'referral.pdf'),
    ],
));
```

Halaxy's referral PATCH endpoint only appends attachments. It does not update
or replace other referral properties:

```php
Halaxy::referrals()->addAttachments(
    $referralId,
    new ReferralAttachment('application/pdf', $base64Data, 'additional.pdf'),
);
```

### Schedules and Slots

```php
$schedules = Halaxy::schedules()->list();

// Generate slots for one schedule via its $generate operation
Halaxy::schedules()->generateSlots('33001', [
    'period' => [
        'start' => '2026-02-01T09:00:00+11:00',
        'end' => '2026-02-08T17:00:00+11:00', // at least 24h after start
    ],
]);

$slots = Halaxy::slots()->list(['schedule' => 'Schedule/33001']);
```

### Region Switching

```php
$response = Halaxy::region('eu')->patients()->find('789');
```

### Multi-Tenant Usage

Each tenant (e.g. clinic) can use its own Halaxy credentials with isolated
OAuth token caching:

```php
$halaxy = Halaxy::for(
    tenantId: $clinic->id,
    clientId: $clinic->halaxy_client_id,
    clientSecret: $clinic->halaxy_client_secret,
    region: 'au',
);

$patients = $halaxy->patients()->list();

// One-off credentials (not cached by tenant)
$halaxy = Halaxy::withCredentials(clientId: '...', clientSecret: '...', region: 'eu');

// Cache management
Halaxy::forgetTenant($clinic->id);
Halaxy::flush();
```

### Direct Client Access

```php
$client = Halaxy::getClient();
$response = $client->get('Patient', ['family' => 'Doe']);
```

## Available Resources

| Group | Method | Operations |
|---|---|---|
| People | `patients()` | find, list, create, update, replace, exportIds |
| People | `practitioners()` | find, list, create |
| People | `practitionerRoles()` | find, list, create |
| People | `organizations()` | find, list, create |
| Scheduling | `appointments()` | find, list, book/create, update, findAvailable |
| Scheduling | `schedules()` | find, list, create, generateSlots |
| Scheduling | `slots()` | find, list |
| Scheduling | `healthcareServices()` | find, list |
| Financial | `chargeItemDefinitions()` | find, list |
| Financial | `coverages()` | find, list, create, update |
| Financial | `invoices()` | find, list |
| Financial | `invoiceLines()` | find, list |
| Financial | `paymentTransactions()` | find, list |
| Financial | `referrals()` | find, list, create, addAttachments |
| Financial | `referralDefinitions()` | find, list |
| Clinical | `documentReferences()` | create |
| Foundations | `capabilities()` | get |
| Foundations | `searchParameters()` | list |

The SDK deliberately exposes only the operations Halaxy documents — there is
no `delete()` anywhere because the Halaxy API has no delete endpoint, and
`replace()` (PUT) exists only on patients.

## Halaxy API Quirks

Verified against the live API — worth knowing before you integrate:

- **Nothing can be deleted.** No resource has a delete endpoint. Records you
  create (patients, contacts, notes) persist until removed via the Halaxy UI.
- **Patient `identifier` and `active` are read-only.** Identifiers sent on
  create are silently dropped, so you cannot tag patients for later lookup;
  store Halaxy patient IDs on your side. Writes to `active` are silently
  ignored — archiving is UI-only.
- **PUT replace is destructive.** Writable properties omitted from a
  `replace()` payload are removed. Send the complete desired state.
- **Patient phone values use compact international format.** Mobile numbers
  use `system=sms`, `use=mobile`; fixed phones use `system=phone` with a
  `home` or `work` purpose. Prefer the typed `ContactPoint` factories so
  invalid payloads fail before an HTTP request is sent.
- **Appointment status is not directly writable.** Book with the `status`
  parameter; cancel via the participant `modifierExtension` (see above).
- **Polymorphic search parameters need `Type/id` values.** e.g.
  `recipient=Patient/123` — a bare ID returns HTTP 422. Some documented
  patient-scoping parameters are silently ignored by the server; verify
  filters against a control query before trusting them.
- **A `User-Agent` header is mandatory.** Halaxy's gateway rejects requests
  without one (HTTP 403). The SDK always sends `halaxy.user_agent`.
- **Most resources cannot be updated at all.** Only `Patient`, `Coverage`,
  `Appointment`, `Referral` and `DocumentReference` accept `patch`.
  `Practitioner`, `PractitionerRole` and `Organization` are create-and-search
  only — a `PATCH` returns HTTP 405 with an empty body. The resource classes
  mirror this by omitting `update()`, so a 405 means something bypassed them
  via `getClient()->patch(...)`. `Halaxy::capabilities()` is the authority:

  ```php
  collect(Halaxy::capabilities()->get()->json()['rest'][0]['resource'])
      ->mapWithKeys(fn ($r) => [$r['type'] => collect($r['interaction'])->pluck('code')->implode(',')]);
  ```

- **Only a practice PractitionerRole may author a DocumentReference.** Halaxy
  keeps two disjoint role namespaces: the practice's own (`PR-…`, profile
  `hx-practitioner-role`, organization `CL-…`) and external referrer roles
  created through the API (`EP-…`, profile `hx-external-practitioner-role`,
  organization `SP-…`). PractitionerRole search returns both, and
  `GET PractitionerRole/EP-…` answers 200 — so a caller cannot distinguish
  them by whether the reference resolves. Naming an external role as `author`
  fails the create with **404 "Author not found"**, which reads like a broken
  reference and is not: the role exists, it is just not an eligible author.
  `author` is optional, so a document whose only known author is an external
  referrer should be filed with no author rather than misattributed to a
  practice clinician who did not write it.

## Webhooks

The SDK integrates `spatie/laravel-webhook-client` to receive Halaxy webhooks.
Webhook handling is disabled by default and must be explicitly enabled with a
signing secret. Requests fail closed if the secret is missing.

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

> [!WARNING]
> `HALAXY_WEBHOOKS_ENABLED=true` without `HALAXY_WEBHOOK_SECRET` rejects every
> webhook request. Configure the same secret as the webhook's Authentication
> Header in Halaxy before enabling the endpoints.

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

Entries named `halaxy`/`halaxy-*` in a host's published `webhook-client.php`
are superseded by the package's entries — put overrides in
`config/halaxy.php`'s `webhooks` block instead.

## Error Handling

```php
use Clinically\Halaxy\Exceptions\AuthenticationException;
use Clinically\Halaxy\Exceptions\MethodNotAllowedException;
use Clinically\Halaxy\Exceptions\NotFoundException;
use Clinically\Halaxy\Exceptions\RateLimitException;
use Clinically\Halaxy\Exceptions\ServerException;
use Clinically\Halaxy\Exceptions\ValidationException;

try {
    $response = Halaxy::patients()->find('invalid-id');
} catch (NotFoundException $e) {
    // 404
} catch (MethodNotAllowedException $e) {
    // 405 — the resource does not support this interaction at all
} catch (ValidationException $e) {
    // 400/422 — $e->operationOutcome holds the FHIR OperationOutcome issues
} catch (AuthenticationException $e) {
    // 401/403 — message includes the OAuth error detail
} catch (RateLimitException $e) {
    $retryAfter = $e->retryAfter;
}
```

Every exception extends `HalaxyException` and carries the status, request
method, request URL and parsed OperationOutcome. Persist
`$e->getOperationOutcome()` when logging a failure — the one-line message is
often too little to diagnose from later.

`isRetryable()` says whether repeating the identical request could plausibly
succeed. A 4xx is a verdict on the request, so retrying one only burns
attempts and multiplies noise in your error tracker; 408, 429 and 5xx (bar
501) are worth another go. Use it to fail a queued job fast:

```php
try {
    Halaxy::documentReferences()->create($payload);
} catch (HalaxyException $e) {
    report($e);

    $e->isRetryable() ? $this->release(60) : $this->fail($e);
}
```

## Testing

```bash
composer test              # Unit + Feature suites (HTTP is faked)
composer test:unit
composer test:feature
composer analyse           # PHPStan
composer lint              # Pint
```

### Integration tests (live API)

The `Integration` suite exercises the real Halaxy API and is excluded from
`composer test`. To run it, copy `.env.example` to `.env`, add credentials
for a practice with **curated test patients**, and pin their IDs:

```env
HALAXY_CLIENT_ID=...
HALAXY_CLIENT_SECRET=...
HALAXY_TEST_PATIENT_IDS=111,222   # first = "Test, Patient", second = "Test, Another"
HALAXY_ALLOW_WRITES=false         # true enables write tests (see .env.example)
```

```bash
composer test:integration
```

Tests skip cleanly when credentials are absent. Write tests are gated behind
`HALAXY_ALLOW_WRITES`, only ever touch the pinned test patients, and clean up
after themselves where the API allows (booked appointments are cancelled);
endpoints that would create undeletable records in the practice are skipped
by design — see `tests/Integration/UnexercisedWriteEndpointsTest.php`.

## License

MIT License. See [LICENSE](LICENSE) for details.
