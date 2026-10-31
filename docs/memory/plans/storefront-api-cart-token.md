---
name: storefront-api-cart-token
description: Storefront API design note: the guest cart token (sealed, no table, bound to the incomplete order and its reference code), its lifetime, how a sign-in adopts the cart, what it may not do
---

## What is a cart token?
- `ct1.<order_id>.<issued>.<mac>`, `mac` = HMAC-SHA256 (ENCRYPTION_KEY) over `order_id|reference_code|issued|app_id`. No table and no column: the order row's `reference_code` (`commerce/order-reference-code-link.md`) is the server-side half, so a token cannot be made from an order id alone.
- Sent in `Pinegrap-Cart-Token`; issued once by `POST /storefront/cart`. Bound to the application that made it: another key presenting it gets 401 `cart_token_invalid`.

## When is it refused?
- Bad shape or MAC, other application, order gone: 401 `cart_token_invalid`.
- The order is no longer `incomplete` (placed, cancelled, deleted): 410 `cart_closed`; a placed order answers `order_number` in the error so an agent can tell "done" from "lost".
- Older than 30 days since `issued`: 401 `cart_token_expired`; the cart row stays (the website's own cart lifetime and cleanup are unchanged).

## Can a token be revoked?
- Changing the order's `reference_code` ends every token of that cart; the cart slice does so when a customer adopts it and when the order is placed. The website's saved-cart link (`?r=`) changes with it, which is the intent: a link to a placed cart no longer brings it back.

## What does a token let the caller do?
- Read and change that one cart, its checkout and its guest order status. Nothing about any customer, any other order, any address book.
- A guest checkout needs `email` on the checkout; the order is a guest order exactly as from the website.

## What happens when the shopper signs in?
- A call carrying both a cart token and a customer token adopts the cart: `orders.user_id` / `contact_id` are set like `initialize_order()`'s rebinding; if the customer already had an open cart the API keeps the newer one and answers its `cart_id` (no line merge in wave 1; the website does not merge either). The reference code is changed (above).

## Why not a session id or a table?
- The API has no PHP session, MCP runs in-process (`api/api-mcp-server.md`), and a table would be schema for a value the order row already proves. A sealed value with a server half is the pattern `pg_cart_quote_token_parts()` (`commerce/cart-quotes.md`) uses.

Related: plans/storefront-api-customer-token.md, plans/storefront-api-callers.md
