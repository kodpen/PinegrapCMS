---
name: customer-rhythm-reorder-link
description: The reorder link of customer.reorder_due (H9): reorder.php, signed products and 30 days, nothing stored, GET shows and POST fills the cart, the ended link, reorder_url
module: customer_rhythm_link
---

## What is the link?
- `reorder.php?p=<id-quantity.…>&e=<end>&s=<signature>` (root): `p` the products the event names with their last quantities (`120-2.8-1`, at most 20, a repeated product adds up), `e` the end, 30 days after the pass start, `s` the first 32 hex of HMAC-SHA256 of `customer-rhythm-reorder:<p>:<e>` under `ENCRYPTION_KEY` (`pg_fs_key()`), compared with `hash_equals`.
- Nothing is stored: the link is its own state, so no column or table. No customer key, contact, order or address is in it, and the page reads no account (H9).
- Built by `pg_customer_rhythm_link_url()` (host `HOSTNAME_SETTING`, as links that leave the request); a site without a key gets `''`.

## Where does it go?
- `customer.reorder_due` carries it as `reorder_url` (field and sample in `webhook_payloads.php`); the pass makes it in `pg_customer_rhythm_tell()` for the products it names. Flows offer it as the output "Reorder link" (`ws_flow_event_outputs()`), so an e-mail, SMS or WhatsApp box can send it.

## What does reorder.php do?
- `pg_customer_rhythm_link_state()`: `open` (signed, end within the next 30 days), `ended` (signed, past its end), `invalid` (anything else, also an end further than 30 days ahead).
- GET on an open link shows the products and quantities only, so a mail scanner or a preview changes nothing; POST with the form token (`validate_token_field()`) runs `pg_customer_rhythm_link_apply()` and sends the browser to the cart (`pg_sw_cart_url()`).
- What goes in is the cart quote's plan (`pg_cart_quote_plan()` on `pg_cart_quote_products()`): a gone, switched off, donation or out of stock product is left out, quantities kept within minimum, maximum and stock; then `add_order_item()`, `update_order_item_prices()`, `apply_offers_to_cart()`, so today's price and offers apply.
- A second POST into the same cart adds nothing: the session keeps link signature => cart (`$_SESSION['ecommerce']['rhythm_links']`, the last 20).
- Ended: status 410, "This link has ended. Sign in to order these products again from your order history." and "Go to my account" when the site has a My Account page; the Reorder button of the order history is `order_history_reorder.php`. Invalid: 404 "This link is not valid.". Commerce off: the invalid card.
- Drawn on `output_header_secure()` + `pg_secure_card()`; `no-store`, `noindex`, `no-referrer`, `DENY`. In `waf_sensitive_scripts()`.

## Tests?
- `tests/customer_rhythm_link_test.php`: items, query names no person, signature prefix, open, ended, changed products, quantity, end or key invalid, parse shapes, `reorder_url` in the event and flow outputs, GET changes nothing, headers, firewall list.
- Not run against a database or in a browser when written (#PR_NUMBER).

Related: docs/memory/commerce/customer-rhythm-events.md, docs/memory/commerce/cart-quotes.md, docs/memory/commerce/order-reference-code-link.md
