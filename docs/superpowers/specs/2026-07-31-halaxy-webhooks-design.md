# Halaxy Webhooks: Tenant-Aware, Path-Per-Event Design

**Date:** 2026-07-31
**Status:** Approved

## Problem

The package already ships webhook support built on `spatie/laravel-webhook-client`
(`HalaxyWebhookProfile`, `HalaxySignatureValidator`, `ProcessHalaxyWebhookJob`,
typed events, auto-registered route). It has two defects:

1. **The typed events can never fire.** Both the profile and the job determine
   the action (created/updated/deleted) by substring-matching the FHIR
   `topic.reference` URL. Halaxy's real payload carries an opaque
   `SubscriptionTopic/{uuid}` reference with no action words, and Halaxy
   publishes no topic-UUID-to-event mapping. Every webhook therefore resolves
   to `*.unknown` and falls through to the generic `HalaxyWebhookReceived`
   event. The payload reliably identifies *which resource* changed
   (`notificationEvent[0].focus.type` and `.reference`) but not *what
   happened* to it.

2. **The wiring is central-only.** The provider force-registers one central
   route and hardcodes its own validator/profile/job into the spatie config.
   Hosts like stream wire webhooks per tenant — routes registered inside a
   `{tenant}`-prefixed, `tenant`-middleware group, with per-tenant signature
   validators — the way notifyre-fax is wired. The current design cannot be
   wired that way.

There is also no test coverage for the pipeline (route, validator, job) —
only the event classes are tested.

## Design decisions

- **Action comes from the URL, not the payload.** In the Halaxy UI you create
  one webhook per event type, so each subscription points at its own endpoint
  path (e.g. `.../halaxy/appointment-created`). Each path maps to a spatie
  `WebhookConfig` whose name encodes the event; the job reads
  `$this->webhookCall->name`. No payload parsing for the action, ever.
- **Package provides components; the host controls wiring** (the notifyre-fax
  pattern). Auto-registered central routes remain for standalone apps but can
  be disabled; a route helper lets hosts register the same routes inside any
  group (tenant prefix + middleware).
- **All wired classes are configurable** so hosts can substitute tenant-aware
  validators or jobs without redefining the spatie config entries.

## Endpoints and config names

Base path: `halaxy.webhooks.path` (default `webhooks/halaxy`). Under it, one
POST route per event plus a generic fallback:

| Path suffix              | Config name                  | Event dispatched      |
| ------------------------ | ---------------------------- | --------------------- |
| `/patient-created`       | `halaxy-patient-created`     | `PatientCreated`      |
| `/patient-updated`       | `halaxy-patient-updated`     | `PatientUpdated`      |
| `/appointment-created`   | `halaxy-appointment-created` | `AppointmentCreated`  |
| `/appointment-updated`   | `halaxy-appointment-updated` | `AppointmentUpdated`  |
| `/appointment-deleted`   | `halaxy-appointment-deleted` | `AppointmentDeleted`  |
| `/invoice-created`       | `halaxy-invoice-created`     | `InvoiceCreated`      |
| `/invoice-updated`       | `halaxy-invoice-updated`     | `InvoiceUpdated`      |
| `/invoice-deleted`       | `halaxy-invoice-deleted`     | `InvoiceDeleted`      |
| *(none — base path)*     | `halaxy`                     | `HalaxyWebhookReceived` |

A typo'd URL in the Halaxy UI matches no route and 404s, surfacing in
Halaxy's troubleshooting email rather than being silently accepted.

## Components

### Routes

- `HalaxyWebhookRoutes::register(?string $prefix = null)` — static helper that
  issues the nine `Route::webhooks($path, $configName)` calls relative to the
  group it is called from. `$prefix` defaults to `halaxy.webhooks.path`.
- Provider `boot()` calls the helper at the configured path only when
  `halaxy.webhooks.register_routes` is true (env `HALAXY_WEBHOOK_ROUTES`,
  default `true`).
- Multi-tenant hosts set `HALAXY_WEBHOOK_ROUTES=false` and call
  `HalaxyWebhookRoutes::register('halaxy')` from their tenant webhook routes
  file (stream: `routes/tenant-webhooks.php`, already grouped under
  `{tenant}/webhooks` with `tenant` middleware). Tenant context is resolved by
  middleware before spatie stores the `WebhookCall` and queues the job, so the
  job and its events run in tenant context. The package itself stays
  tenant-agnostic — no tenancy dependency.

### Spatie config registration

Provider `register()` merges nine `webhook-client.configs` entries (one per
name above), all built from `halaxy.webhooks`:

- `signing_secret` ← `halaxy.webhooks.signing_secret`
- `signature_header_name` ← `halaxy.webhooks.signature_header_name`
  (default `Authorization`)
- `signature_validator` ← `halaxy.webhooks.signature_validator`
  (default `HalaxySignatureValidator::class`)
- `webhook_profile` ← `halaxy.webhooks.webhook_profile`
  (default spatie `ProcessEverythingWebhookProfile::class`)
- `process_webhook_job` ← `halaxy.webhooks.process_webhook_job`
  (default `ProcessHalaxyWebhookJob::class`)
- `webhook_model` ← `halaxy.webhooks.webhook_model`
  (default spatie `WebhookCall::class`)

Gated by `halaxy.webhooks.enabled` as today. Registration of the spatie
provider is kept. A host needing per-tenant secrets swaps
`signature_validator` for its own class (mirroring stream's
`TenantNotifyreFaxSignatureValidator`); everything else stays.

### ProcessHalaxyWebhookJob

- Maps `$this->webhookCall->name` (`halaxy-patient-created` → `patient.created`)
  to the typed event via an explicit match.
- The generic `halaxy` config name dispatches `HalaxyWebhookReceived` with
  `eventType` derived from `focus.type` (e.g. `patient.unknown`), plus the
  resource reference and timestamp extracted as today.
- Topic-URL substring parsing is deleted.
- Typed event constructor signatures are unchanged
  (`resourceReference`, `timestamp`, `webhookCall`).

### Removals

- `HalaxyWebhookProfile` (and its duplicate broken parser) — replaced by
  spatie's `ProcessEverythingWebhookProfile`. Event selection is done by which
  URLs you register in Halaxy, so the `halaxy.webhooks.events` filter config
  is removed too.

### HalaxySignatureValidator

Exact-match or `Bearer `-stripped comparison of the configured header against
the signing secret; rejects all requests when no secret is configured.

## Config changes (`config/halaxy.php` → `webhooks`)

- Keep: `enabled`, `signing_secret`, `signature_header_name`, `path`,
  `delete_after_days`, `queue_connection`, `queue`.
- Add: `register_routes` (env `HALAXY_WEBHOOK_ROUTES`, default `true`),
  `signature_validator`, `webhook_profile`, `process_webhook_job`,
  `webhook_model` (class-string overrides, defaulting to package/spatie
  classes).
- Remove: `events`.

## Testing

Feature tests using the verbatim payload from Halaxy's docs as a fixture:

- POST to each event route stores a `WebhookCall` under the right config name
  and dispatches the matching typed event with correct
  `resourceReference`/`timestamp`.
- POST to the base path dispatches `HalaxyWebhookReceived` with correct
  resource type and ID from `focus`.
- Signature validation: exact match accepted, `Bearer <secret>` accepted,
  wrong/missing header rejected with a non-2xx response and no stored
  `WebhookCall`; no secret configured rejects all requests.
- `register_routes=false` registers no routes; `enabled=false` registers
  neither routes nor spatie configs.
- `HalaxyWebhookRoutes::register()` inside a prefixed + middleware'd group
  produces working tenant-style URLs.
- Config overrides for `signature_validator` / `process_webhook_job` are
  honored.

Unit tests: config-name → event mapping in the job; existing event-class
tests remain.

## Documentation

README webhooks section rewritten around two wirings:

1. **Standalone:** auto routes, one secret, CSRF exclusion
   `webhooks/halaxy/*`, Halaxy UI setup listing the per-event URLs.
2. **Multi-tenant:** `HALAXY_WEBHOOK_ROUTES=false`, call
   `HalaxyWebhookRoutes::register()` in a tenant-prefixed group, custom
   validator for per-tenant secrets.

## Out of scope

- Tenancy machinery in the package (stays host-side).
- Topic-UUID mapping (undocumented by Halaxy).
- Webhook subscription management via API (Halaxy is UI-only).
