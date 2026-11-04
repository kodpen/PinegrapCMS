---
name: external-api-coverage
description: External API (integration.php): find what the panel can do that the API cannot, then add the missing endpoints in steps (owner, 2026-11-01); claim as [plan:external-api-coverage] X<n>
---

## What did the owner ask?
- Endpoints for what the panel manages and `integration.php` lacks. Not the storefront API nor the vendor client.

## Which steps are there?
- X0 gap list: done (PR #5239). X1–X12 are `free`, most useful first. A write step first moves the screen's inline SQL into one `includes/fn/` function both call. Store writes take `store:write` (commerce ceiling); store reads stay under `orders:read`.
- X1a zone and tax zone writes, X1b shipping methods: PRs #5377, #5491 (`api/api-store-zone-writes.md`, `api/api-store-shipping-method-writes.md`).
- X2 Returns read and moves: PR #5925 (`api/api-returns.md`).
- X2b `modified_since` on `GET /returns` through `order_returns.updated_at` (#5927): PR #6600.
- X3 subscriptions read, commission/affiliate writes: PR #5741 (`api/api-commission-writes.md`, `api/api-subscriptions-list.md`).
- X4 Users read: `GET /users`, `/users/{id}` (role, permission columns, last sign-in, MFA; no secrets). New `users:read`, designer ceiling. Writes: X13 (`api/api-users-write.md`). PR #5898 (`api/api-users-read.md`).
- X5a countries and states, X5b currencies and rate refresh, `store:write`: PRs #6005, #6051 (`api/api-store-country-state-writes.md`, `api/api-store-currency-writes.md`).
- X6 `POST /orders` as `add_order.php`, Idempotency-Key, dry run, `orders:write`, event `order.created`, cut in two:
  - X6a `local_sale_complete()` into `includes/fn/local_order.php` off the session cart: PR #6107 (`erp/erp-counter-sale-completion.md`).
  - X6b The route on it: PR #6148 (`api/api-orders-create.md`).
- X7 `POST /offers`, `POST /offers/{id}/duplicate` through the editor's checks and save, `offers:write`, events `offer.created|updated|deleted`: PR #6175 (`api/api-offers-create.md`).
- X8 Store lookup lists, cut in three:
  - X8a the eight screens' write path (`fn/store_lookups.php`), `/store/referral-sources` CRUD: PR #6247 (`api/api-store-lookups.md`).
  - X8b `/store/ship-date-adjustments`, `/store/verified-addresses` CRUD (409 `adjustment_taken`): PR #6308.
  - X8c `/store/arrival-dates` CRUD: PR #6332 (`api/api-store-arrival-dates.md`).
- X9 Campaigns, `campaigns:*`, cut in three:
  - X9a `/campaigns/profiles` CRUD: PR #6353 (`api/api-campaign-profiles.md`).
  - X9b `GET /campaigns/sms`, event `campaign.sent`: PR #6480 (`api/api-sms-campaigns.md`).
  - X9c `POST /campaigns` (plain text), `/schedule`: PR #6502. HTML open (`api/api-campaign-create.md`).
- X10 Calendars, cut in two:
  - X10a Calendars create/update/delete (`fn/calendar_writes.php`): PR #6375 (`api/api-calendar-writes.md`).
  - X10b locations CRUD, event reservations, `calendar_event.reserved`: PR #6415 (`api/api-calendar-locations-reservations.md`).
- X11 Form submissions write, `GET /forms/{id}/fields`: PR #6424.
- X12 `/design/` CRUD, `design:*`, no events:
  - X12a ads, ad regions: PR #6383 (`api/api-design-ads.md`).
  - X12b auto dialogs, common regions: PR #6615 (`api/api-design-dialogs-regions.md`).

## What is not a step?
- Redirects: short links with `redirect_code` and `*` patterns, covered. Saved reports and the back-in-stock list wait for a client. Settings, firewall, sessions, backups, updates: no endpoint (#5238, `api/api-settings-no-endpoint.md`).
- #5085 (bell `group`, `fold`) is free work outside the steps.

## What is settled?
- Each endpoint follows `api/README.md`: one schema row, a scope with its owner ceiling, the role-3 row rule, `api_request_input()`, `api_dry_run_stop()` above the first write, POST twins, `max_length` within the column, events through `pg_announce()`.
- It reuses the screen's write function, so the screen's rules hold. Each step tests its handlers; `php tools/check_api_schema.php` covers the row.

## Where to start?
- `api/README.md`, `api-endpoint-rules.md`, `includes/api/schema.php`, `includes/api/resources/`.
