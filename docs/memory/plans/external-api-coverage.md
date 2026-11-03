---
name: external-api-coverage
description: External API (integration.php): find what the panel can do that the API cannot, then add the missing endpoints in steps (owner, 2026-11-01); claim as [plan:external-api-coverage] X<n>
---

## What did the owner ask?
- Endpoints for what the panel manages and `integration.php` lacks. Not the storefront API nor the vendor client.

## Which steps are there?
- X0 gap list: done (PR #5239). X1–X12 are `free`, most useful first. A write step first moves the screen's inline SQL into one `includes/fn/` function both call. Store writes take `store:write` (commerce ceiling); store reads stay under `orders:read`.
- X1a Zone and tax zone writes (create, update, `/delete`, GET one), `store:write`, events `zone.*`, `tax_zone.*`: PR #5377 (`api/api-store-zone-writes.md`).
- X1b Shipping method writes (create, update, `/delete`), `store:write`, events `shipping_method.*`: PR #5491 (`api/api-store-shipping-method-writes.md`).
- X2 Returns: `GET /returns` (status, order_id), `GET /returns/{id}`, `POST /returns/{id}` (approve, reject, ship, receive, close, refund as `view_return.php`). `orders:*`. Events: existing `order.return.*`: PR #5925 (`api/api-returns.md`).
- X2b `modified_since` on `GET /returns` through `order_returns.updated_at` (decided in #5927).
- X3 `GET /subscriptions`, `/subscriptions/{id}` (`customers:read`); `POST /commissions/{id}` status, `POST /affiliates/{id}/approve` (`customers:write`, the contact screen's right), `GET /commissions/recurring-profiles`; new `commissions:write`. Events `commission.status_changed`, `affiliate.approved`: PR #5741 (`api/api-commission-writes.md`, `api/api-subscriptions-list.md`).
- X4 Users read: `GET /users`, `/users/{id}` (role, permission columns, last sign-in, MFA; no secrets). New `users:read`, designer ceiling. Writes: X13 (`external-api-coverage-2.md`). PR #5898 (`api/api-users-read.md`).
- X5a Countries and states CRUD, `store:write`: PR #6005 (`api/api-store-country-state-writes.md`).
- X5b Currencies CRUD and `POST /store/currencies/rates`, `store:write`, events `currency.*`: PR #6051 (`api/api-store-currency-writes.md`).
- X6 `POST /orders` as `add_order.php`, Idempotency-Key, dry run, `orders:write`, event `order.created`, cut in two:
  - X6a `local_sale_complete()` moves into `includes/fn/local_order.php`, free of the session cart; the screen calls it, tests pin it.
  - X6b The route on it: lines, customer, payment, dry run via its check.
- X7 `POST /offers`, `POST /offers/{id}/duplicate` (`edit_offer_f.php` rules). `offers:write`. New events `offer.created|updated|deleted`.
- X8 CRUD `/store/arrival-dates`, `/store/ship-date-adjustments`, `/store/verified-addresses`, `/store/referral-sources`. `orders:read`, `store:write`. No events.
- X9 `POST /campaigns` (draft), `POST /campaigns/{id}/schedule`, campaign profiles CRUD, `GET /campaigns/sms`. `campaigns:*`. Event `campaign.sent`.
- X10 Calendars create/update/delete, `/calendars/locations` CRUD, `GET /calendars/events/{id}/reservations`. `calendars:*` with the role-3 row rule. Event `calendar_event.reserved`.
- X11 `POST /forms/{id}/submissions`, its `/delete`, `GET /forms/{id}/fields`. `forms:*`. Event `form.submission.deleted`; `form.submitted` exists.
- X12 CRUD for ads, ad regions, auto dialogs, common regions under `/design/`. `design:*`. No events.

## What is not a step?
- Redirects: short links with `redirect_code` and `*` patterns, covered. Saved reports and the back-in-stock list wait for a client. Settings, firewall, sessions, backups, updates: no endpoint (#5238, `external-api-coverage-2.md`).
- Issues #3411, #3412, #3413 (PR #3842), #3414 are done per `api/` memory and can be closed; #5085 (bell `group`, `fold`) is free work outside the steps.

## What is settled?
- Each endpoint follows `api/README.md`: one schema row, a scope with its owner ceiling, the role-3 row rule, `api_request_input()`, `api_dry_run_stop()` above the first write, POST twins, `max_length` within the column, events through `pg_announce()`.
- It reuses the screen's write function, so the screen's rules hold. Each step tests its handlers; `php tools/check_api_schema.php` covers the row.

## Where to start?
- `api/README.md`, `api-endpoint-rules.md`, `includes/api/schema.php`, `includes/api/resources/`.
