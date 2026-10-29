---
name: cart-quotes
description: Cart quotes (Sepet teklifi): cart_quotes / cart_quote_items, the hashed link, cart_quote.php (GET confirm, POST fills the cart), plan rules, ordered on completion, contact holder, export and erasure
module: cart_quotes
---

## What is a cart quote?
- Staff's list of products and quantities for a customer, sent as a link; it fills the visitor's cart at the catalogue's prices of that moment (owner answer 3b, schema #3072). Never a per-line price, not the saved-cart link (`order-reference-code-link.md`), not a Sales quote (`erp_quotes`).
- Tables (step 11.0.124 `upgrade_2026_11_0_cart_quotes()`, both in `get_tables()`): `cart_quotes` (`token_hash` CHAR(64) UNIQUE, `token_hint` VARCHAR(8), `contact_id`, `valid_until`, `status` open/ordered/expired/cancelled, `order_id`, `created_by`, `created_at`, `updated_at`; times unix seconds) and `cart_quote_items` (`cart_quote_id`, `product_id`, `quantity`). Module `includes/fn/cart_quotes.php`, readiness `pg_cart_quotes_ready()`.

## How does the link work?
- Token from `pg_short_link_new_token()`, kept as SHA-256 plus its first 6 characters (`pg_cart_quote_token_parts()`), the `ws_approval_guests` pattern; returned once by `pg_cart_quote_create()`.
- `cart_quote.php?t=` (root, session on: the visitor's cart): GET only shows the products and quantities (`pg_cart_quote_view()`), so mail scanners and previews change nothing; POST with the form token (`validate_token_field()`) runs `pg_cart_quote_apply()` and sends the browser to the cart (`pg_sw_cart_url()`). Never shows the contact. `noindex`, `no-store`, `no-referrer`, `DENY` framing. In `waf_sensitive_scripts()`, so GET and POST count on the 30/min allowance (token guessing).
- `pg_cart_quote_plan()` (pure) holds each line against the product now: gone (`missing`), disabled or donation (`unavailable`), out of stock without backorder or less stock than the minimum (`stock`) are left out; quantity raised to the minimum, cut to the maximum and, without backorder, to the stock. Lines go in by `add_order_item($id, $qty, 0, 'myself', '')`, then `update_order_item_prices()` and `apply_offers_to_cart()`.
- The quote remembers the cart it filled (`order_id`, still `open`); a second POST into the same cart adds nothing. `pg_order_complete()` calls `pg_cart_quote_order_completed()` after the order number: `status = 'ordered'` where `order_id` matches and status is open, from the checkout and a payment webhook. Another cart filled later takes `order_id` over.
- `pg_cart_quote_state()`: an open quote past `valid_until` (0 = no end) reads as expired; nothing writes `expired` yet.

## Personal data?
- `cart_quotes.contact_id` is a `pg_contact_holders()` holder (merge `move`, erase `find`); erasure anonymises `contact_id = 0` (products kept for the store); the export's `cart_quotes` section lists the person's quotes with their items, never the token.

## Where does staff work with them?
- `view_cart_quotes.php` (Orders menu after Returns; `?contact_id=` one contact's, `?id=` one quote with its Workspace button): newest 200, state badge, hint; an open quote gets Cancel (`pg_cart_quote_cancel()`) or New link (`pg_cart_quote_new_link()`, the old one stops). The link is shown once.
- `add_cart_quote.php`: customer box and 10 product boxes (`data-pg-combobox` with its own `?request=contacts|products` search, donations not offered), quantities, Valid until (default 30 days, `pg_cart_quote_until()`: end of that day, empty = no end).
- Workspace: the customer card's "Open cart quotes" (`ws_customer_cart_quote_rows()`: open, unexpired, line counts, no amount) links to the contact's list; its "New cart quote" opens `add_cart_quote.php?contact_id=` (#3197, `workspace/workspace-customer-card.md`). Tag `cart_quote` (`#sepet`, #3256): `workspace/workspace-cart-quote-tag.md`.

## What is not built yet?
- `cart_quote.*` API routes and events, a sweep writing `expired` (`plans/workspace-quotes.md`).

Related: docs/memory/plans/workspace-quotes.md, docs/memory/core/contact-holders.md, docs/memory/core/personal-data-erasure.md
