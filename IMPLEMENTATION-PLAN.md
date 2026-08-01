# Halaxy Laravel SDK - Implementation Plan

## Package Overview

- **Package**: `clinically/laravel-halaxy`
- **Namespace**: `Clinically\Halaxy`
- **Requirements**: PHP 8.5+, Laravel 12+, PHPStan Level 6, Pint formatting
- **Architecture**: Resource-based fluent API with typed DTOs
- **HTTP Client**: Laravel HTTP Client (Illuminate\Http\Client)
- **Webhooks**: `spatie/laravel-webhook-client` integration

---

## API Overview

### Halaxy API Details
- **Standard**: FHIR R4B (Fast Healthcare Interoperability Resources)
- **Content-Type**: `application/fhir+json`
- **Authentication**: OAuth2 with client credentials (tokens valid 15 minutes)
- **Base URLs**:
  - AU/Other: `https://au-api.halaxy.com/main/`
  - EU/UK: `https://eu-api.halaxy.com/main/`

---

## Complete Endpoint Inventory (18 Resources, 48 Endpoints)

### People Resources (15 endpoints)

#### Patient (6 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Patient/{id}` | `patients()->find($id)` | Get single patient |
| GET | `/Patient` | `patients()->list()` | List patients with filtering |
| POST | `/Patient` | `patients()->create($data)` | Create new patient |
| PATCH | `/Patient/{id}` | `patients()->update($id, $data)` | Partial update patient |
| PUT | `/Patient/{id}` | `patients()->replace($id, $data)` | Full replace patient |
| GET | `/Patient/$export` | `patients()->exportIds()` | Export patient references |

#### Practitioner (3 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Practitioner/{id}` | `practitioners()->find($id)` | Get single practitioner |
| GET | `/Practitioner` | `practitioners()->list()` | List practitioners |
| POST | `/Practitioner` | `practitioners()->create($data)` | Create practitioner (professional contact) |

#### PractitionerRole (3 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/PractitionerRole/{id}` | `practitionerRoles()->find($id)` | Get single role |
| GET | `/PractitionerRole` | `practitionerRoles()->list()` | List roles |
| POST | `/PractitionerRole` | `practitionerRoles()->create($data)` | Create role (professional contact) |

#### Organization (3 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Organization/{id}` | `organizations()->find($id)` | Get single organization |
| GET | `/Organization` | `organizations()->list()` | List organizations |
| POST | `/Organization` | `organizations()->create($data)` | Create organization (professional contact practice) |

### Scheduling Resources (13 endpoints)

#### Appointment (5 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Appointment/{id}` | `appointments()->find($id)` | Get single appointment |
| GET | `/Appointment` | `appointments()->list()` | List appointments |
| POST | `/Appointment/$book` | `appointments()->book($data)` | Book new appointment |
| PATCH | `/Appointment/{id}` | `appointments()->update($id, $data)` | Update appointment |
| GET | `/Appointment/$find-available` | `appointments()->findAvailable($params)` | Find available slots |

#### Schedule (4 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Schedule/{id}` | `schedules()->find($id)` | Get single schedule |
| GET | `/Schedule` | `schedules()->list()` | List schedules |
| POST | `/Schedule` | `schedules()->create($data)` | Create schedule |
| POST | `/Schedule/$generate-slots` | `schedules()->generateSlots($data)` | Generate schedule slots |

#### Slot (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Slot/{id}` | `slots()->find($id)` | Get single slot |
| GET | `/Slot` | `slots()->list()` | List slots |

#### HealthcareService (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/HealthcareService/{id}` | `healthcareServices()->find($id)` | Get single service |
| GET | `/HealthcareService` | `healthcareServices()->list()` | List services |

### Financial Resources (17 endpoints)

#### ChargeItemDefinition (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/ChargeItemDefinition/{id}` | `chargeItemDefinitions()->find($id)` | Get single definition |
| GET | `/ChargeItemDefinition` | `chargeItemDefinitions()->list()` | List definitions |

#### Coverage (4 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Coverage/{id}` | `coverages()->find($id)` | Get single coverage |
| GET | `/Coverage` | `coverages()->list()` | List coverages |
| POST | `/Coverage` | `coverages()->create($data)` | Create coverage |
| PATCH | `/Coverage/{id}` | `coverages()->update($id, $data)` | Update coverage |

#### Invoice (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Invoice/{id}` | `invoices()->find($id)` | Get single invoice |
| GET | `/Invoice` | `invoices()->list()` | List invoices |

#### InvoiceLine (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/InvoiceLine/{id}` | `invoiceLines()->find($id)` | Get single line |
| GET | `/InvoiceLine` | `invoiceLines()->list()` | List lines |

#### PaymentTransaction (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/PaymentTransaction/{id}` | `paymentTransactions()->find($id)` | Get single transaction |
| GET | `/PaymentTransaction` | `paymentTransactions()->list()` | List transactions |

#### Referral (4 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/Referral/{id}` | `referrals()->find($id)` | Get single referral |
| GET | `/Referral` | `referrals()->list()` | List referrals |
| POST | `/Referral` | `referrals()->create($data)` | Create referral |
| PATCH | `/Referral/{id}` | `referrals()->addAttachments($id, ...$attachments)` | Append referral attachments |

#### ReferralDefinition (2 endpoints)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/ReferralDefinition/{id}` | `referralDefinitions()->find($id)` | Get single definition |
| GET | `/ReferralDefinition` | `referralDefinitions()->list()` | List definitions |

### Clinical Resources (1 endpoint)

#### DocumentReference (1 endpoint)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| POST | `/DocumentReference` | `documentReferences()->create($data)` | Create document reference |

### Foundation Resources (2 endpoints)

#### CapabilityStatement (1 endpoint)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/metadata` | `capabilities()->get()` | Get API capabilities |

#### SearchParameter (1 endpoint)
| Method | Endpoint | SDK Method | Description |
|--------|----------|------------|-------------|
| GET | `/SearchParameter` | `searchParameters()->list()` | List search parameters |

---

## Implementation Phases

### Phase 1: Core Infrastructure
| Item | Files | Est. Tests |
|------|-------|------------|
| Composer setup, PHPStan, Pint config | `composer.json`, `phpstan.neon`, `pint.json` | - |
| Config file | `config/halaxy.php` | 5 |
| Service Provider | `HalaxyServiceProvider.php` | 10 |
| Facade | `Facades/Halaxy.php` | 5 |
| HTTP Client wrapper | `Http/Client.php` | 15 |
| OAuth2 Authenticator with caching | `Http/Authenticator.php` | 20 |
| Response wrapper | `Http/Response.php` | 10 |
| Query Builder | `Queries/QueryBuilder.php` | 25 |
| Paginator | `Pagination/FhirPaginator.php` | 15 |
| Exception classes (5) | `Exceptions/*.php` | 10 |
| Base Resource class | `Resources/Resource.php` | 10 |
| Main Halaxy client | `Halaxy.php` | 15 |

### Phase 2: FHIR Value Objects & DTOs
| Item | Description |
|------|-------------|
| `Identifier` | FHIR Identifier type |
| `HumanName` | FHIR HumanName type |
| `Address` | FHIR Address type |
| `ContactPoint` | FHIR ContactPoint type (phone, email, etc.) |
| `Reference` | FHIR Reference type |
| `CodeableConcept` | FHIR CodeableConcept type |
| `Coding` | FHIR Coding type |
| `Period` | FHIR Period type |
| `Meta` | FHIR Meta type |
| `Bundle` | FHIR Bundle (collection response) |

### Phase 3: People Resources
- PatientResource + Patient DTO (6 endpoints)
- PractitionerResource + Practitioner DTO (3 endpoints)
- PractitionerRoleResource + PractitionerRole DTO (3 endpoints)
- OrganizationResource + Organization DTO (3 endpoints)

### Phase 4: Scheduling Resources
- AppointmentResource + Appointment DTO (5 endpoints)
- ScheduleResource + Schedule DTO (4 endpoints)
- SlotResource + Slot DTO (2 endpoints)
- HealthcareServiceResource + HealthcareService DTO (2 endpoints)

### Phase 5: Financial Resources
- ChargeItemDefinitionResource + ChargeItemDefinition DTO (2 endpoints)
- CoverageResource + Coverage DTO (4 endpoints)
- InvoiceResource + Invoice DTO (2 endpoints)
- InvoiceLineResource + InvoiceLine DTO (2 endpoints)
- PaymentTransactionResource + PaymentTransaction DTO (2 endpoints)
- ReferralResource + Referral DTO (4 endpoints)
- ReferralDefinitionResource + ReferralDefinition DTO (2 endpoints)

### Phase 6: Clinical & Foundations
- DocumentReferenceResource + DocumentReference DTO (1 endpoint)
- CapabilityStatementResource + CapabilityStatement DTO (1 endpoint)
- SearchParameterResource + SearchParameter DTO (1 endpoint)

### Phase 7: Webhook Integration (Spatie)
- HalaxySignatureValidator
- HalaxyWebhookProfile
- HalaxyWebhookCall model
- ProcessHalaxyWebhookJob
- Domain events (PatientCreated, AppointmentUpdated, etc.)

---

## Directory Structure

```
clinically/laravel-halaxy/
├── AGENTS.md
├── IMPLEMENTATION-PLAN.md
├── README.md
├── LICENSE
├── composer.json
├── phpstan.neon
├── pint.json
├── phpunit.xml
├── config/
│   └── halaxy.php
├── database/
│   └── migrations/
│       └── (optional webhook table extensions)
├── src/
│   ├── Halaxy.php                        # Main client entry point
│   ├── HalaxyServiceProvider.php         # Laravel service provider
│   ├── Facades/
│   │   └── Halaxy.php                    # Laravel facade
│   ├── Contracts/
│   │   ├── HalaxyClientInterface.php
│   │   ├── AuthenticatorInterface.php
│   │   ├── ResourceInterface.php
│   │   └── DtoInterface.php
│   ├── Http/
│   │   ├── Client.php                    # HTTP client wrapper
│   │   ├── Authenticator.php             # OAuth2 token management
│   │   └── Response.php                  # Response wrapper
│   ├── Resources/
│   │   ├── Resource.php                  # Abstract base
│   │   ├── Concerns/
│   │   │   ├── HasFind.php
│   │   │   ├── HasList.php
│   │   │   ├── HasCreate.php
│   │   │   ├── HasUpdate.php
│   │   │   └── HasDelete.php
│   │   ├── People/
│   │   │   ├── PatientResource.php
│   │   │   ├── PractitionerResource.php
│   │   │   ├── PractitionerRoleResource.php
│   │   │   └── OrganizationResource.php
│   │   ├── Scheduling/
│   │   │   ├── AppointmentResource.php
│   │   │   ├── ScheduleResource.php
│   │   │   ├── SlotResource.php
│   │   │   └── HealthcareServiceResource.php
│   │   ├── Financial/
│   │   │   ├── ChargeItemDefinitionResource.php
│   │   │   ├── CoverageResource.php
│   │   │   ├── InvoiceResource.php
│   │   │   ├── InvoiceLineResource.php
│   │   │   ├── PaymentTransactionResource.php
│   │   │   ├── ReferralResource.php
│   │   │   └── ReferralDefinitionResource.php
│   │   ├── Clinical/
│   │   │   └── DocumentReferenceResource.php
│   │   └── Foundations/
│   │       ├── CapabilityStatementResource.php
│   │       └── SearchParameterResource.php
│   ├── DTOs/
│   │   ├── Dto.php                       # Abstract base
│   │   ├── Patient.php
│   │   ├── Practitioner.php
│   │   ├── PractitionerRole.php
│   │   ├── Organization.php
│   │   ├── Appointment.php
│   │   ├── Schedule.php
│   │   ├── Slot.php
│   │   ├── HealthcareService.php
│   │   ├── ChargeItemDefinition.php
│   │   ├── Coverage.php
│   │   ├── Invoice.php
│   │   ├── InvoiceLine.php
│   │   ├── PaymentTransaction.php
│   │   ├── Referral.php
│   │   ├── ReferralDefinition.php
│   │   ├── DocumentReference.php
│   │   ├── CapabilityStatement.php
│   │   └── SearchParameter.php
│   ├── ValueObjects/
│   │   ├── Identifier.php
│   │   ├── HumanName.php
│   │   ├── Address.php
│   │   ├── ContactPoint.php
│   │   ├── Reference.php
│   │   ├── CodeableConcept.php
│   │   ├── Coding.php
│   │   ├── Period.php
│   │   ├── Meta.php
│   │   └── Attachment.php
│   ├── Collections/
│   │   ├── Bundle.php                    # FHIR Bundle wrapper
│   │   └── FhirCollection.php
│   ├── Queries/
│   │   └── QueryBuilder.php
│   ├── Pagination/
│   │   └── FhirPaginator.php
│   ├── Exceptions/
│   │   ├── HalaxyException.php
│   │   ├── AuthenticationException.php
│   │   ├── ValidationException.php
│   │   ├── NotFoundException.php
│   │   └── RateLimitException.php
│   ├── Webhooks/
│   │   ├── HalaxySignatureValidator.php
│   │   ├── HalaxyWebhookProfile.php
│   │   ├── HalaxyWebhookCall.php
│   │   └── ProcessHalaxyWebhookJob.php
│   ├── Events/
│   │   ├── HalaxyWebhookReceived.php
│   │   ├── Patient/
│   │   │   ├── PatientCreated.php
│   │   │   └── PatientUpdated.php
│   │   ├── Appointment/
│   │   │   ├── AppointmentCreated.php
│   │   │   ├── AppointmentUpdated.php
│   │   │   └── AppointmentDeleted.php
│   │   └── Invoice/
│   │       ├── InvoiceCreated.php
│   │       ├── InvoiceUpdated.php
│   │       └── InvoiceDeleted.php
│   ├── Enums/
│   │   ├── Region.php
│   │   ├── AppointmentStatus.php
│   │   ├── SlotStatus.php
│   │   └── InvoiceStatus.php
│   └── Commands/
│       ├── ValidateCredentialsCommand.php
│       └── ClearCacheCommand.php
└── tests/
    ├── TestCase.php
    ├── Fixtures/
    │   └── (JSON response fixtures)
    ├── Unit/
    │   ├── DTOs/
    │   ├── ValueObjects/
    │   └── Queries/
    ├── Feature/
    │   ├── Resources/
    │   ├── Http/
    │   └── Commands/
    └── Integration/
        ├── IntegrationTestCase.php
        └── Resources/
```

---

## Usage Examples

### Basic Usage

```php
use Clinically\Halaxy\Facades\Halaxy;

// Find a patient
$patient = Halaxy::patients()->find('123456');

// List patients with query
$patients = Halaxy::patients()
    ->query()
    ->where('given', 'contains', 'John')
    ->where('_lastUpdated', 'gt', '2024-01-01')
    ->include('generalPractitioner')
    ->paginate(50);

// Create a patient
$patient = Halaxy::patients()->create([
    'name' => [
        ['given' => ['John'], 'family' => 'Doe']
    ],
    'birthDate' => '1990-01-15',
]);

// Book an appointment
$appointment = Halaxy::appointments()->book([
    'patient' => 'Patient/123',
    'practitionerRole' => 'PractitionerRole/456',
    'start' => '2025-02-15T09:00:00+10:00',
    'end' => '2025-02-15T09:30:00+10:00',
]);
```

### EU Region

```php
// Use EU region
$euClient = Halaxy::region('eu');
$patient = $euClient->patients()->find('789');

// Or via config
// HALAXY_REGION=eu in .env
```

### Webhook Handling

```php
// In EventServiceProvider
protected $listen = [
    \Clinically\Halaxy\Events\Patient\PatientCreated::class => [
        \App\Listeners\SyncPatientToLocalDatabase::class,
    ],
    \Clinically\Halaxy\Events\Appointment\AppointmentUpdated::class => [
        \App\Listeners\NotifyPatientOfAppointmentChange::class,
    ],
];
```

---

## Test Estimates

| Category | Count |
|----------|-------|
| Unit Tests (DTOs, ValueObjects, QueryBuilder) | ~150 |
| Feature Tests (Resources, HTTP, Commands) | ~200 |
| Integration Tests (Real API, optional) | ~50 |
| **Total** | **~400** |

---

## Configuration Options

```php
// config/halaxy.php
return [
    'client_id' => env('HALAXY_CLIENT_ID'),
    'client_secret' => env('HALAXY_CLIENT_SECRET'),
    'region' => env('HALAXY_REGION', 'au'), // 'au' or 'eu'
    
    'base_urls' => [
        'au' => 'https://au-api.halaxy.com/main/',
        'eu' => 'https://eu-api.halaxy.com/main/',
    ],
    
    'user_agent' => env('HALAXY_USER_AGENT', 'Clinically Halaxy SDK'),
    
    'cache' => [
        'enabled' => env('HALAXY_CACHE_ENABLED', true),
        'store' => env('HALAXY_CACHE_STORE', null), // null = default
        'ttl' => env('HALAXY_CACHE_TTL', 300), // 5 minutes
        'prefix' => 'halaxy',
    ],
    
    'webhooks' => [
        'enabled' => env('HALAXY_WEBHOOKS_ENABLED', true),
        'signing_secret' => env('HALAXY_WEBHOOK_SECRET'),
        'signature_header_name' => 'Authorization',
        'path' => env('HALAXY_WEBHOOK_PATH', 'webhooks/halaxy'),
        'delete_after_days' => 30,
    ],
];
```

---

## Dependencies

```json
{
    "require": {
        "php": "^8.5",
        "illuminate/support": "^12.0",
        "illuminate/http": "^12.0",
        "illuminate/cache": "^12.0",
        "spatie/laravel-webhook-client": "^3.4"
    },
    "require-dev": {
        "orchestra/testbench": "^10.0",
        "phpstan/phpstan": "^2.0",
        "laravel/pint": "^1.18",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0"
    }
}
```
