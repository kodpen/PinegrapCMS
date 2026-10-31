---
name: cart-quotes-api
description: Cart quotes in the external API (PR #3298): the five routes, orders scopes, dry run, the shared *_check() functions with the screens, the link answered once, the cart_quote.* webhook events
module: cart_quotes
---

## Which routes are there?
- In `api_schema()`, handlers in `includes/api/resources/cart_quotes.php`: `GET /cart-quotes` (`cart_quotes.list`: `state`, `contact_id`, `order_id`, `created_since`, cursor by id, newest first, each with its lines), `GET /cart-quotes/{id}`, `POST /cart-quotes` (201), `POST /cart-quotes/{id}/cancel`, `POST /cart-quotes/{id}/link`.
- Reads ask `orders:read`, writes `orders:write`: the cart quote screens open to the ecommerce right, as the order screens, and the store hands the order scopes to the same people. The Workspace tag reads with the same scope (`workspace/workspace-cart-quote-tag.md`).
- Objects `CartQuote` / `CartQuoteLine` (`api_cart_quote_schema()` / `_present()`, pairs in `tools/check_api_schema.php`). A line carries the product as the catalogue has it now; `name` and `short_description` are null for a product that is gone. A quote keeps no price.
- `state` is `pg_cart_quote_state()`: open, expired (open past `valid_until`), ordered, cancelled; `status` is the stored column.

## How is the link kept safe?
- Only the answers that make one (`create`, `link`) carry `link`; every other answer has it null and gives only `token_hint`. Both routes list `link` in `idempotency_redact`, so a retry answered from the Idempotency-Key record carries `link` null.
- The event payload never carries the link either.

## Which checks do the screens and the API share?
- `pg_cart_quote_create_check()` (tables, then the pure `pg_cart_quote_create_refusal()`): the contact is there, `valid_until` is not over, lines clean (at most `PG_CART_QUOTE_MAX_LINES`), each product in the catalogue, out of the Recycle Bin, enabled, no donation. `add_cart_quote.php` puts a `contact_id` / `valid_until` refusal on its field.
- `pg_cart_quote_open_check()` (pure part `pg_cart_quote_open_refusal()`): the quote is there and open now; cancel and new link both need it.
- The API maps a refusal in `api_cart_quote_refuse()`: `not_installed` 503 `service_unavailable`, `not_found` 404, `not_open` 409 `cart_quote_not_open` (named in the routes' descriptions, so no catalogue row), a field refusal 422 `validation_failed`.

## Dry run?
- All three writes are `dry_run`; `api_dry_run_stop()` sits after the shared check, directly above the write (`api/api-dry-run.md`). A rehearsal makes no link and announces nothing.
- A quote that stops being open between the check and the write answers 409 too.

## Which events are announced?
- `cart_quote.created`, `.applied` (with `added`; the same cart again is not announced), `.ordered` (with `order_number`, from `pg_cart_quote_order_completed()`, one event per quote that moved), `.cancelled`; in `api_webhook_events()`. Sent by `pg_cart_quote_announce()` from the cart quote functions, so screens, API, link page and order completion alike.
- Payload `pg_cart_quote_event_payload()` (pure): id, status, state, contact_id, order_id, valid_until (UTC or null), items (product_id, quantity).
- Not announced: a new link, and a quote running past its last day (nothing writes `expired`).

## Site Log?
- The caller writes it: the API names the application and key; `view_cart_quotes.php` logs cancel and new link.

## Tests?
- `tests/api_cart_quotes_test.php` (routes and scopes, presenter, state filter, last day, the shared checks above the dry-run stop, events in the catalogue and announced where written), `tests/cart_quotes_test.php` (refusals, payload), `test_ws_api_search_record_scopes_name_known_kinds` (the tag's scope equals `cart_quotes.list` / `.get`).

Related: docs/memory/commerce/cart-quotes.md, docs/memory/api/api-webhook-events.md, docs/memory/plans/workspace-quotes.md
