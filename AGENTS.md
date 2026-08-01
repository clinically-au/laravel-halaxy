# Halaxy Laravel SDK - Agent Guidelines

## Project Overview

A Laravel 12+ SDK for the Halaxy FHIR R4B healthcare API.

**Package**: `clinically/laravel-halaxy`  
**Namespace**: `Clinically\Halaxy`  
**Requirements**: PHP 8.5+, Laravel 12+, PHPStan Level 6, Pint formatting

## Quick Reference

- **API Docs**: https://developers.halaxy.com/reference
- **API Standard**: FHIR R4B
- **Auth**: OAuth2 client credentials (15 min token expiry)
- **Content-Type**: `application/fhir+json`
- **Base URLs**:
  - AU: `https://au-api.halaxy.com/main/`
  - EU: `https://eu-api.halaxy.com/main/`

## Architecture Decisions

### Design Principles

1. **Resource-based fluent API**: `$halaxy->patients()->find($id)`
2. **Strongly typed DTOs**: All request/response objects are typed
3. **Immutable value objects**: FHIR types (HumanName, Address, etc.)
4. **Interface-driven**: All major components implement contracts
5. **Full Laravel integration**: Config, Facades, Caching, Events

### Key Patterns

```php
// Fluent resource access
Halaxy::patients()->find($id);
Halaxy::patients()->list();
Halaxy::patients()->create($data);
Halaxy::patients()->update($id, $data);

// Query building
Halaxy::patients()
    ->query()
    ->where('given', 'contains', 'John')
    ->where('_lastUpdated', 'gt', '2024-01-01')
    ->include('generalPractitioner')
    ->paginate(50);

// Region switching
Halaxy::region('eu')->patients()->find($id);
```

### Dependencies

- `spatie/laravel-webhook-client` for webhook handling
- Laravel HTTP Client for API requests
- Laravel Cache for token/response caching

## Code Standards

### PHPStan Level 6 Requirements

- All methods MUST have explicit return types
- All parameters MUST be typed
- Use `@param` and `@return` PHPDoc for generics
- No `mixed` types without explicit documentation
- Strict null checking with `?Type` or union types

```php
// Good
public function find(string $id): Patient

/** @return Collection<int, Patient> */
public function list(): Collection

// Bad
public function find($id)  // Missing types
public function list(): mixed  // Avoid mixed
```

### Pint Configuration

Uses Laravel preset with additional rules:
- `declare_strict_types`: true
- `final_class`: true (most classes)
- `ordered_imports`: alphabetical
- `no_unused_imports`: true

### File Headers

Every PHP file MUST start with:

```php
<?php

declare(strict_types=1);

namespace Clinically\Halaxy\...;
```

### Class Structure

```php
<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources;

use Clinically\Halaxy\Contracts\ResourceInterface;
use Clinically\Halaxy\DTOs\Patient;
// ... other imports alphabetically

final class PatientResource implements ResourceInterface
{
    // 1. Constants
    // 2. Properties  
    // 3. Constructor
    // 4. Public methods
    // 5. Protected methods
    // 6. Private methods
}
```

## Directory Structure

```
src/
├── Halaxy.php                    # Main client entry point
├── HalaxyServiceProvider.php     # Laravel service provider
├── Facades/Halaxy.php            # Laravel facade
├── Contracts/                    # Interfaces
├── Http/                         # HTTP layer
│   ├── Client.php                # HTTP client wrapper
│   ├── Authenticator.php         # OAuth2 token management
│   └── Response.php              # Response wrapper
├── Resources/                    # API resource classes
│   ├── Resource.php              # Abstract base
│   ├── Concerns/                 # Traits for CRUD operations
│   ├── People/                   # Patient, Practitioner, etc.
│   ├── Scheduling/               # Appointment, Schedule, etc.
│   ├── Financial/                # Invoice, Coverage, etc.
│   ├── Clinical/                 # DocumentReference
│   └── Foundations/              # Metadata, SearchParameter
├── DTOs/                         # Data Transfer Objects
├── ValueObjects/                 # FHIR data types
├── Collections/                  # Bundle, FhirCollection
├── Queries/QueryBuilder.php      # FHIR query builder
├── Pagination/FhirPaginator.php  # Laravel-compatible paginator
├── Exceptions/                   # Custom exceptions
├── Webhooks/                     # Spatie webhook integration
├── Events/                       # Laravel events
├── Enums/                        # Status enums, etc.
└── Commands/                     # Artisan commands
```

## FHIR-Specific Guidelines

### Value Objects

FHIR data types should be immutable value objects:

```php
final readonly class HumanName
{
    public function __construct(
        public ?string $use = null,
        public ?string $text = null,
        public ?string $family = null,
        /** @var list<string> */
        public array $given = [],
        /** @var list<string> */
        public array $prefix = [],
        /** @var list<string> */
        public array $suffix = [],
        public ?Period $period = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            use: $data['use'] ?? null,
            text: $data['text'] ?? null,
            // ...
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'use' => $this->use,
            'text' => $this->text,
            // ...
        ], fn ($v) => $v !== null && $v !== []);
    }
}
```

### DTOs

Resource DTOs should be readonly classes with factory methods:

```php
final readonly class Patient
{
    public function __construct(
        public string $id,
        public string $resourceType,
        public Meta $meta,
        /** @var list<Identifier> */
        public array $identifier,
        /** @var list<HumanName> */
        public array $name,
        // ...
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            resourceType: $data['resourceType'],
            meta: Meta::fromArray($data['meta'] ?? []),
            identifier: array_map(
                Identifier::fromArray(...),
                $data['identifier'] ?? []
            ),
            // ...
        );
    }
}
```

### Query Parameters

Follow FHIR search parameter conventions:

| Type | Examples |
|------|----------|
| String | `name`, `given:contains`, `family:exact` |
| Token | `identifier`, `status` |
| Reference | `patient`, `practitioner` |
| Date | `birthdate`, `_lastUpdated` |
| Number | `_count`, `page` |
| Special | `_id`, `_include`, `_sort`, `_summary` |

## Webhook Integration

Using `spatie/laravel-webhook-client`:

### Components

| Class | Purpose |
|-------|---------|
| `HalaxySignatureValidator` | Validates auth header |
| `HalaxyWebhookProfile` | Filters webhook types |
| `ProcessHalaxyWebhookJob` | Parses FHIR Bundle, dispatches events |
| `HalaxyWebhookCall` | Extended model for Halaxy-specific data |

### Events Dispatched

- `HalaxyWebhookReceived` (generic)
- `Patient\PatientCreated`
- `Patient\PatientUpdated`
- `Appointment\AppointmentCreated`
- `Appointment\AppointmentUpdated`
- `Appointment\AppointmentDeleted`
- `Invoice\InvoiceCreated`
- `Invoice\InvoiceUpdated`
- `Invoice\InvoiceDeleted`

## Testing Strategy

### Unit Tests

- Test DTOs, value objects, query builder in isolation
- Mock all external dependencies
- Test edge cases and validation
- Located in `tests/Unit/`

### Feature Tests

- Test resource classes with mocked HTTP responses
- Test full request/response cycles
- Test Laravel integration (config, caching)
- Use fixtures from `tests/Fixtures/`
- Located in `tests/Feature/`

### Integration Tests

- Require `HALAXY_TEST_*` env vars
- Test against real sandbox/test API
- Marked with `@group integration`
- Skipped by default in CI
- Located in `tests/Integration/`

### Test Naming

```php
// Unit tests
test('patient dto can be created from array')
test('human name formats full name correctly')
test('query builder generates correct parameters')

// Feature tests  
test('can retrieve a patient by id')
test('can list patients with pagination')
test('authentication token is cached')

// Integration tests
test('can create and retrieve patient from api')
```

## Error Handling

### Exception Hierarchy

```
HalaxyException (base)
├── AuthenticationException (401)
├── ForbiddenException (403)
├── NotFoundException (404)
├── ValidationException (400, 422)
├── RateLimitException (429)
└── ServerException (500, 503)
```

### Exception Information

All exceptions should include:
- HTTP status code
- Halaxy error message
- Request details (method, URL)
- FHIR OperationOutcome if available

## Caching Strategy

### Token Caching

- OAuth tokens cached for (expiry - 60 seconds)
- Cache key: `halaxy.token.{region}`
- Automatic refresh before expiry

### Response Caching (Optional)

- Configurable TTL per resource type
- Cache key: `halaxy.{resource}.{id}` or `halaxy.{resource}.list.{hash}`
- Invalidated on mutations

## Common Tasks

### Adding a New Resource

1. Create DTO in `src/DTOs/`
2. Create Resource class in `src/Resources/{Category}/`
3. Add accessor method to `Halaxy.php`
4. Add tests in `tests/Unit/DTOs/` and `tests/Feature/Resources/`
5. Update README if needed

### Adding a New Value Object

1. Create class in `src/ValueObjects/`
2. Implement `fromArray()` and `toArray()` methods
3. Make class `final readonly`
4. Add comprehensive unit tests

### Adding a New Webhook Event

1. Create event class in `src/Events/{Resource}/`
2. Update `ProcessHalaxyWebhookJob` to dispatch it
3. Document in README

## API Endpoints Reference

See `IMPLEMENTATION-PLAN.md` for complete endpoint listing.

### Summary

| Category | Resources | Endpoints |
|----------|-----------|-----------|
| People | Patient, Practitioner, PractitionerRole, Organization | 15 |
| Scheduling | Appointment, Schedule, Slot, HealthcareService | 13 |
| Financial | ChargeItemDefinition, Coverage, Invoice, InvoiceLine, PaymentTransaction, Referral, ReferralDefinition | 17 |
| Clinical | DocumentReference | 1 |
| Foundations | CapabilityStatement, SearchParameter | 2 |
| **Total** | **18** | **48** |

## Environment Variables

```env
# Required
HALAXY_CLIENT_ID=your-client-id
HALAXY_CLIENT_SECRET=your-client-secret

# Optional
HALAXY_REGION=au                    # au or eu
HALAXY_USER_AGENT="Your App Name"   # Identifies your application
HALAXY_CACHE_ENABLED=true           # Enable response caching
HALAXY_CACHE_TTL=300                # Cache TTL in seconds

# Webhooks
HALAXY_WEBHOOKS_ENABLED=true
HALAXY_WEBHOOK_SECRET=your-webhook-secret
HALAXY_WEBHOOK_PATH=webhooks/halaxy

# Testing (Integration tests only)
HALAXY_TEST_CLIENT_ID=test-client-id
HALAXY_TEST_CLIENT_SECRET=test-client-secret
```
