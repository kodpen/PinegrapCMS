---
name: storefront-api-errors
description: Storefront API design note: error codes and HTTP statuses in the existing api_fail() shape, the 409s named by their routes, and the recoverable messages[] on cart and checkout answers
---

## Which shape?
- The existing one, `api_fail()` (`includes/api/response.php`): `{"error": {"code", "message", "request_id", "field"?, "docs"?}}`. Generic codes are reused (`validation_failed`, `not_found`, `rate_limited`, `insufficient_scope`, `idempotency_*`, `dry_run_unsupported`).
- New non-409 codes go into `api_error_catalogue()` (`tools/check_api_schema.php` fails otherwise); 409s are described on their routes (`api/api-endpoint-rules.md`).

## Which new catalogue codes?
| Code | HTTP | When |
|---|---|---|
| `idempotency_key_required` | 400 | confirm or payment intent without the header |
| `cart_token_required` | 401 | cart route with neither cart nor customer token |
| `cart_token_invalid` | 401 | shape, MAC, application or order wrong |
| `cart_token_expired` | 401 | older than 30 days |
| `customer_token_required` | 401 | customer route without a customer token |
| `customer_token_invalid` | 401 | unknown, revoked or bad MAC |
| `customer_token_expired` | 401 | access token past its hour: refresh |
| `sign_in_failed` | 401 | wrong user or password (not told apart) |
| `cart_closed` | 410 | the cart's order is no longer incomplete |
| `unsupported_cart` | 422 | the cart needs a feature the API does not drive |
| `payment_method_unavailable` | 422 | method not offered, or not drivable by the API |
| `payment_declined` | 402 | the driver refused; `message` carries the service's words |

## Which 409s, on which routes?
- `total_changed` (confirm): the posted `total` is below what would be charged; the answer holds the new totals.
- `out_of_stock` (lines add/update, confirm): `field` names the line; the quantity left is in the error.
- `address_undeliverable` (shipping choice, checkout update, confirm): no shipping method reaches the address.
- `coupon_invalid` (coupon): unknown, expired, used up or its conditions unmet; the offer's own reason in `message`.
- `email_taken` (sign-up), `checkout_incomplete` (confirm before e-mail, address or shipping is set; `field` names the first missing).
- `out_of_stock` and `address_undeliverable` are the UCP vocabulary words on purpose (`storefront-api-acp-ucp.md`).

## What is `messages[]`?
- Every `StorefrontCart` / checkout answer carries `messages[]`: `{"type": "error"|"warning"|"info", "code", "message", "path"}`, for states that do not fail the call: a price that moved since the line was added, a line reduced to the stock left, an offer no longer met, a shipping method gone. Codes reuse the 409 words where they mean the same.
- The source is the warnings the express order already shows (`commerce/cart-express-order-warnings.md`), mapped to codes, not a second check.
- Texts in `api_text_language()` (`api/api-meta-language-timezone.md`), through `lang()`.

Related: plans/storefront-api-callers.md
