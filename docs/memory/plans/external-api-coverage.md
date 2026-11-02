---
name: external-api-coverage
description: External API (integration.php): find what the panel can do that the API cannot, then add the missing endpoints in steps (owner, 2026-11-01); claim as [plan:external-api-coverage] X<n>
---

## What did the owner ask?
- Endpoints for what the panel manages and `integration.php` lacks. Not the storefront API nor the vendor client.

## Which steps are there?
- X0 gap list: done (PR #5239). X1–X12 are `free`, most useful first. A write step first moves the screen's inline SQL into one `includes/fn/` function both call. Store writes take `store:write` (commerce ceiling); store reads stay under `orders:read`.
- X1a Zone and tax zone writes (create, update, `/delete`, GET one), `store:write`, events `zone.*`, `tax_zone.*`: PR #X1PR (`api/api-store-zone-writes.md`).
- X1b Shipping method writes: create, update, `/delete` for `/store/shipping-methods` through a write function shared with `add_/edit_shipping_method.php` (`pg_shipping_method_form_columns()` reads the panel's formatted text; the API needs its own typed input). `store:write`. Events `shipping_method.created|updated|deleted`. Free.
- X2 Returns: `GET /returns` (status, order_id, modified_since), `GET /returns/{id}`, `POST /returns/{id}` (approve, receive, refund, reject as `view_return.php`). `orders:*`. Events: existing `order.return.*`.
- X3 `GET /subscriptions`, `/subscriptions/{id}` (`customers:read`); `POST /commissions/{id}` status, `POST /affiliates/{id}/approve`, `GET /commissions/recurring-profiles`; new `commissions:write`. Events `commission.status_changed`, `affiliate.approved`.
- X4 Users read: `GET /users`, `/users/{id}` (role, permission columns, last sign-in, MFA; no secrets). New `users:read`, designer ceiling. Writes: #5237.
- X5 Countries, states, currencies: create, update, delete; `POST /store/currencies/rates` (`update_exchange_rates.php`). `store:write`. Event `currency.updated`.
- X6 `POST /orders` as `add_order.php`, Idempotency-Key, dry run. `orders:write`. Event `order.created`.
- X7 `POST /offers`, `POST /offers/{id}/duplicate` (`edit_offer_f.php` rules). `offers:write`. New events `offer.created|updated|deleted`.
- X8 CRUD `/store/arrival-dates`, `/store/ship-date-adjustments`, `/store/verified-addresses`, `/store/referral-sources`. `orders:read`, `store:write`. No events.
- X9 `POST /campaigns` (draft), `POST /campaigns/{id}/schedule`, campaign profiles CRUD, `GET /campaigns/sms`. `campaigns:*`. Event `campaign.sent`.
- X10 Calendars create/update/delete, `/calendars/locations` CRUD, `GET /calendars/events/{id}/reservations`. `calendars:*` with the role-3 row rule. Event `calendar_event.reserved`.
- X11 `POST /forms/{id}/submissions`, its `/delete`, `GET /forms/{id}/fields`. `forms:*`. Event `form.submission.deleted`; `form.submitted` exists.
- X12 CRUD for ads, ad regions, auto dialogs, common regions under `/design/`. `design:*`. No events.

## What is not a step?
- Redirects: short links with `redirect_code` and `*` patterns, covered. Saved reports and the back-in-stock list wait for a client. Settings, firewall, sessions, backups, updates: #5238.
- Issues #3411, #3412, #3413 (PR #3842), #3414 are done per `api/` memory and can be closed; #5085 (bell `group`, `fold`) is free work outside the steps.

## What is settled?
- Each endpoint follows `api/README.md`: one schema row, a scope with its owner ceiling, the role-3 row rule, `api_request_input()`, `api_dry_run_stop()` above the first write, POST twins, `max_length` within the column, events through `pg_announce()`.
- It reuses the screen's write function, so the screen's rules hold. Each step tests its handlers; `php tools/check_api_schema.php` covers the row.

## Where to start?
- `api/README.md`, `api-endpoint-rules.md`, `includes/api/schema.php`, `includes/api/resources/`.
