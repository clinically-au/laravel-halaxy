# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

## v1.4.0 - 2026-08-17

### Added

- Added `MethodNotAllowedException` for HTTP 405. Halaxy answers a rejected
  method with an empty body, so this previously fell through to a generic
  `HalaxyException` reading "An unknown error occurred"; the message now names
  the method, the endpoint, and any `Allow` header. It extends
  `HalaxyException`, so existing catch blocks are unaffected.
- Added `HalaxyException::isRetryable()` so callers can fail a queued job
  immediately on a 4xx instead of retrying a request the API has already
  ruled on. 408, 429 and 5xx (bar 501) remain retryable.

### Documentation

- Documented the per-resource interaction matrix: only `Patient`, `Coverage`,
  `Appointment`, `Referral` and `DocumentReference` accept `patch`.
  `Practitioner`, `PractitionerRole` and `Organization` are create-and-search
  only and answer `PATCH` with 405.
- Documented that only a practice `PractitionerRole` (`PR-…`) may author a
  `DocumentReference`. External referrer roles (`EP-…`) resolve to 200 on a
  GET but fail the create with 404 "Author not found"; `author` is optional,
  so such documents should be filed unattributed. Pinned by write-gated
  integration coverage.

## v1.3.0 - 2026-08-13

### Added

- Added typed patient payload and contact-point value objects for patient
  create, update, and replace operations while retaining raw-array support.

### Fixed

- Mobile patient contacts now use Halaxy's `sms` / `mobile` shape, and phone
  values are rejected before sending unless they use compact international
  format.
- Patient email and fixed-phone contact points always include a supported
  purpose.

## v1.2.1 - 2026-08-01

### Added

- Added a typed Halaxy referral payload and attachment value object.
- Added an attachment-only referral update helper that rejects unsupported
  property updates before sending them to Halaxy.

## v1.2.0 - 2026-08-01

### Added

- Initial open-source release of the Laravel SDK for the Halaxy FHIR R4B API.
- Resource-based API access, FHIR query building, pagination, OAuth token
  caching, multi-region support, and optional webhook handling.
- MIT license and an explicit notice that this is an independent, unofficial
  integration that is not affiliated with or endorsed by Halaxy.

### Security

- Webhooks are disabled by default and must be explicitly enabled.
- Webhook validation fails closed when no non-blank secret is configured.
- Documentation and test fixtures use synthetic identifiers.
