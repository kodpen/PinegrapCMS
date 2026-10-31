---
name: storefront-api-callers
description: Storefront API design note: who calls it (application key + optional cart or customer token), the storefront:* scopes, rate buckets, Idempotency-Key and dry-run rules shared by every route
---

## Who calls a storefront route?
- Always an application: a `server` key (HTTP Basic) or an OAuth connection holding `storefront:*` scopes; the shopper rides on top in a second header. No new `api_apps.kind`: a headless front end or an agent platform is a server application like any integration.
- Shopper headers: `Pinegrap-Cart-Token` (guest cart, `storefront-api-cart-token.md`) and `Pinegrap-Customer-Token` (signed-in customer, `storefront-api-customer-token.md`). Read only through `api_header()`, so MCP runs them in-process.
- `api_current_app()['owner']` stays the key's owner (logs, audit); the shopper is `api_storefront_shopper()`: `array('kind' => 'guest'|'customer', 'user_id', 'contact_id', 'order_id')`. A handler never uses the owner as the buyer.

## Which scopes?
- `storefront:read` (public catalogue view, shipping methods, payment methods), `storefront:cart`, `storefront:checkout`, `storefront:customer`. One group in `api_scope_groups()` with four rows; write does not imply another family.
- Owner ceiling (`api_owner_scopes()`): an owner with the e-commerce right (`manage_ecommerce`, or role 0-2). No `storefront:*` scope implies `orders:*` or `customers:*`: the shopper sees only their own cart, orders and addresses.
- Device sessions (team phones) never hold `storefront:*` (`api_device_scopes()` drops the family): a staff member is not a shopper.

## Which rate limits?
- The application's bucket as today (`api_apps.rate_limit_per_min`, `includes/api/ratelimit.php`).
- Inside it, per shopper, the WAF rate store (`waf_rate_hits()` / `waf_rate_record()`, new scopes in `waf_rate_long_scopes()` where the window is long):

| Scope | Key | Limit |
|---|---|---|
| `sfc` cart writes | cart token | 120 / 10 min |
| `sfo` checkout confirm | cart token | 10 / hour |
| `sfi` sign-in | e-mail + address | the website sign-in throttle, same counters |
| `sfu` sign-up | address | 5 / hour |
| `sfp` password reset | e-mail | the website's reset limit |

- A shopper limit answers 429 `rate_limited` with `Retry-After`, like the app bucket.

## Which writes take an Idempotency-Key?
- Every storefront POST, through the existing reservation (`api-idempotency-key.md`). A guest's key is hashed with the cart id, a customer's with the token's selector (the `api_idempotency_scoped_key()` pattern), so two shoppers behind one application never share a key.
- Required (400 `idempotency_key_required`, new catalogue code) on `checkout.confirm` and `checkout.payment_intent`: a retried confirm must never charge twice.
- `customer.sign_in` and `customer.sign_up` carry `'idempotency_redact' => array('customer_token')` with a restore reading the row back.

## Where does dry-run apply?
- Cart writes, coupon, shipping choice and `checkout.confirm`: `api_dry_run_stop()` above the first write after every check; a rehearsed confirm answers the totals and the stock and address verdicts, charges nothing.
- Not on sign-in, sign-up, password reset or `checkout.payment_intent` (the driver call is the side effect): `dry_run_unsupported`.

## Which existing rules hold?
- Rows in `api_schema()` (or `includes/api/storefront.php` returning rows the schema merges), presenters `api_storefront_*_present()`, money in minor units, POST twins for deletes (`external-api-iis-environment.md`).

Related: plans/storefront-api-agentic-commerce.md, plans/storefront-api-routes-cart.md
