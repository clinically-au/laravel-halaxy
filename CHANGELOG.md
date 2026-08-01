# Changelog

All notable changes to this project will be documented in this file.

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
