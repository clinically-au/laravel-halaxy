## clinically/laravel-halaxy

This package is a Laravel SDK for the Halaxy FHIR R4B healthcare API. All access goes through the `Halaxy` facade (or the injected `HalaxyManager` for multi-tenant apps).

### Architecture

- **Namespace**: `Clinically\Halaxy\`
- **Package owns**: HTTP client, OAuth token handling, resource classes, query builder, paginator, webhook events, typed exceptions
- **Consuming app owns**: credentials (config/.env), tenant credential storage, webhook listeners, persistence of Halaxy IDs

### Core Classes

| Class | Purpose |
|---|---|
| `Facades\Halaxy` | Entry point — `Halaxy::patients()`, `Halaxy::appointments()`, etc. |
| `HalaxyManager` | Multi-tenant instance manager (`for()`, `withCredentials()`) |
| `Http\Response` | Wraps API responses: `json()`, `successful()`, `entries()`, `isBundle()`, `total()`, `nextPageUrl()` |
| `Queries\QueryBuilder` | FHIR search: `where()`, `include()`, `orderBy()`, `paginate()`, `first()` |
| `Pagination\FhirPaginator` | Page iteration; `all()` lazily walks every page |

### Features

- Resource access: every documented Halaxy endpoint, and only those. No resource has `delete()` (the API has none); only `patients()` has `replace()` (PUT). Example:

@verbatim
<code-snippet name="Resource usage" lang="php">
use Clinically\Halaxy\DTOs\ContactPoint;
use Clinically\Halaxy\DTOs\PatientPayload;

$patient = Halaxy::patients()->find('123456')->json();

$bundle = Halaxy::patients()->query()
    ->where('family', 'Doe')
    ->paginate(50);

Halaxy::patients()->update('123456', new PatientPayload(
    telecom: [ContactPoint::mobile('+61400000000')],
));
</code-snippet>
@endverbatim

- Appointment booking via the `$book` operation — the payload is a FHIR **Parameters** resource, never a bare Appointment:

@verbatim
<code-snippet name="Book an appointment" lang="php">
Halaxy::appointments()->book(['parameter' => [
    ['name' => 'appt-resource', 'resource' => [
        'resourceType' => 'Appointment',
        'start' => $start, 'end' => $end, 'minutesDuration' => 30,
        'participant' => [['actor' => ['type' => 'PractitionerRole', 'reference' => $roleUrl]]],
    ]],
    ['name' => 'patient-id', 'valueReference' => ['type' => 'Patient', 'reference' => $patientUrl]],
    ['name' => 'healthcare-service-id', 'valueReference' => ['type' => 'HealthcareService', 'reference' => $serviceUrl]],
    ['name' => 'location-type', 'valueCode' => 'clinic'],
    ['name' => 'status', 'valueCode' => 'booked'],
]]);
</code-snippet>
@endverbatim

- Multi-tenant credentials with isolated token caching:

@verbatim
<code-snippet name="Multi-tenant usage" lang="php">
$halaxy = Halaxy::for(
    tenantId: $clinic->id,
    clientId: $clinic->halaxy_client_id,
    clientSecret: $clinic->halaxy_client_secret,
);
$halaxy->patients()->list();
</code-snippet>
@endverbatim

### Halaxy API rules (verified live — do not fight these)

- Nothing is deletable via the API. Never generate a `delete()` call; records are removed only in the Halaxy UI.
- Patient `identifier` and `active` are read-only: identifiers on create are silently dropped (you cannot tag patients — store Halaxy IDs in the consuming app), and `active` writes are silently ignored.
- `replace()` (PUT, patients only) removes writable properties omitted from the payload — always send the complete desired state.
- Appointments have no writable `status`; cancel by patching the patient participant's `modifierExtension` with coding `cancelled (no charge)` (system `.../presets/CodeSystem/appointment-participant-status`).
- Polymorphic search parameters (e.g. invoice `recipient`) require `Type/id` values (`Patient/123`); bare IDs return HTTP 422.
- `update()` is JSON merge-patch; `Patient` writable fields are name, telecom, gender, birthDate, deceasedBoolean, address, contact (attachments is write-only).
- Patient phone values must use compact international format. Use `ContactPoint::mobile()` for Halaxy's required `sms` / `mobile` shape and `ContactPoint::phone()` for fixed `home` or `work` numbers.

### Error handling

Failed responses throw typed exceptions from `Clinically\Halaxy\Exceptions`: `AuthenticationException`, `NotFoundException`, `ValidationException` (carries the FHIR `operationOutcome`), `RateLimitException` (has `retryAfter`), `ServerException`. Catch these rather than checking status codes.

### Conventions

- `declare(strict_types=1)` in all PHP files; `final` classes; full parameter/return types
- Credentials come from `config('halaxy.*')` — never `env()` in package code
- `composer test` runs unit/feature suites (HTTP faked); `composer test:integration` hits the live API and requires `.env` credentials — never run it unless explicitly asked
