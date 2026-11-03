---
name: external-api-coverage
description: External API (integration.php): find what the panel can do that the API cannot, then add the missing endpoints in steps (owner, 2026-11-01); claim as [plan:external-api-coverage] X<n>
---

## What did the owner ask?
- Endpoints for what the panel manages and `integration.php` lacks. Not the storefront API nor the vendor client.

## Which steps are there?
- X0 gap list: done (PR #5239). X1–X12 are `free`, most useful first. A write step first moves the screen's inline SQL into one `includes/fn/` function both call. Store writes take `store:write` (commerce ceiling); store reads stay under `orders:read`.
- X1a Zone and tax zone writes: PR #5377 (`api/api-store-zone-writes.md`).
- X1b Shipping method writes: PR #5491 (`api/api-store-shipping-method-writes.md`).
- X2 Returns read and moves: PR #5925 (`api/api-returns.md`).
- X2b `modified_since` on `GET /returns` through `order_returns.updated_at` (decided in #5927).
- X3 subscriptions read, commission and affiliate writes: PR #5741 (`api/api-commission-writes.md`, `api/api-subscriptions-list.md`).
- X4 Users read: `GET /users`, `/users/{id}` (role, permission columns, last sign-in, MFA; no secrets). New `users:read`, designer ceiling. Writes: X13 (`external-api-coverage-2.md`). PR #5898 (`api/api-users-read.md`).
- X5a Countries and states CRUD, `store:write`: PR #6005 (`api/api-store-country-state-writes.md`).
- X5b Currencies CRUD and rate refresh: PR #6051 (`api/api-store-currency-writes.md`).
- X6 `POST /orders` as `add_order.php`, Idempotency-Key, dry run, `orders:write`, event `order.created`, cut in two:
  - X6a `local_sale_complete()` moves into `includes/fn/local_order.php`, free of the session cart; the screen calls it, tests pin it: PR #6107 (`erp/erp-counter-sale-completion.md`).
  - X6b The route on it: PR #6148 (`api/api-orders-create.md`).
- X7 `POST /offers`, `POST /offers/{id}/duplicate` through the editor's validate and save, `offers:write`, events `offer.created|updated|deleted`: PR #6175 (`api/api-offers-create.md`).
- X8 Store lookup lists, cut in three:
  - X8a Shared write path of the eight screens (`fn/store_lookups.php`), `/store/referral-sources` CRUD: PR #6247 (`api/api-store-lookups.md`).
  - X8b `/store/ship-date-adjustments`, `/store/verified-addresses` CRUD (409 `adjustment_taken`): PR #6308.
  - X8c `/store/arrival-dates` CRUD with `shipping_cutoffs` (a row the screen drops is 422): PR #6332 (`api/api-store-arrival-dates.md`).
- X9 `POST /campaigns` (draft), `POST /campaigns/{id}/schedule`, campaign profiles CRUD, `GET /campaigns/sms`. `campaigns:*`. Event `campaign.sent`.
- X10 Calendars create/update/delete, `/calendars/locations` CRUD, `GET /calendars/events/{id}/reservations`. `calendars:*` with the role-3 row rule. Event `calendar_event.reserved`.
- X11 `POST /forms/{id}/submissions`, its `/delete`, `GET /forms/{id}/fields`. `forms:*`. Event `form.submission.deleted`; `form.submitted` exists.
- X12 `/design/` CRUD, `design:*`, no events:
  - X12a ads, ad regions: PR #PRNUM (`api/api-design-ads.md`).
  - X12b auto dialogs, common regions; a converted common region refuses delete as its screen does (#5516).

## What is not a step?
- Redirects: short links with `redirect_code` and `*` patterns, covered. Saved reports and the back-in-stock list wait for a client. Settings, firewall, sessions, backups, updates: no endpoint (#5238, `external-api-coverage-2.md`).
- #5085 (bell `group`, `fold`) is free work outside the steps.

## What is settled?
- Each endpoint follows `api/README.md`: one schema row, a scope with its owner ceiling, the role-3 row rule, `api_request_input()`, `api_dry_run_stop()` above the first write, POST twins, `max_length` within the column, events through `pg_announce()`.
- It reuses the screen's write function, so the screen's rules hold. Each step tests its handlers; `php tools/check_api_schema.php` covers the row.

## Where to start?
- `api/README.md`, `api-endpoint-rules.md`, `includes/api/schema.php`, `includes/api/resources/`.
