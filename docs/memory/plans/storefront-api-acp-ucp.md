---
name: storefront-api-acp-ucp
description: Storefront API design note: OpenAI ACP checkout_sessions mapped onto the storefront routes, the UCP manifest after checkout exists, MCP tools under the customer's consent, WAF agent verification
---

## How does ACP map?
| ACP | Storefront call |
|---|---|
| POST /acp/checkout_sessions | `cart.create` + `lines.add` per item + `checkout.create` (buyer, address) |
| GET /acp/checkout_sessions/{id} | `checkout.get` |
| POST /acp/checkout_sessions/{id} | `lines.*` / `checkout.update` / `cart.shipping` (fulfillment option) |
| POST /acp/checkout_sessions/{id}/complete | `checkout.payment_intent` + `checkout.confirm` |
| POST /acp/checkout_sessions/{id}/cancel | close the cart (reference code changed, status kept `incomplete`) |

- The ACP session id is the cart token: the agent platform keeps it, no table. ACP handlers are thin translators in `includes/api/acp.php` calling the storefront handlers' cores; no logic of their own.
- `API-Version` header checked against the versions the binding supports; `Idempotency-Key` is the storefront one; ACP `messages[]` and `fulfillment_options` come from our `messages[]` and `shipping-methods`.
- `order_created` / `order_updated`: the existing outbound webhook client signs them (HMAC) to the platform's endpoint, a subscription the operator makes in the ACP settings card.
- Payment: delegated tokens only where a driver has a network-token or shared-token path (`pg_payment_can()`); Stripe shared payment tokens are not offered in Türkiye yet (watch item).
- Auth: ACP sends `Authorization: Bearer <merchant key>`; the ACP slice maps a Bearer value to a server application key holding `storefront:*` (open question below).

## When does UCP come?
- `/.well-known/ucp` only once the checkout routes run in production: capability `dev.ucp.shopping.checkout` pointing at the storefront checkout, error words `out_of_stock` / `address_undeliverable` and recoverable `messages[]` already in place (`storefront-api-errors.md`).
- The feed is the existing one (`commerce/product-feed-serving.md`); checkout eligibility in `pg_product_feed_openai_row()` switches on in the same step (`commerce/product-feed-decisions.md`).

## How do storefront routes become MCP tools?
- Like every route (`api/api-mcp-server.md`): the rows are tools; cart and checkout tools for an OAuth connection holding `storefront:*`. Confirm and payment intent carry `'mcp' => false` until the customer's consent screen exists: an agent never places an order the shopper did not approve.
- The customer's consent: the shopper signs in on the website (not the panel) and approves the connection; the resulting token is a customer token (open question below).

## What does the WAF slice do?
- Verify Web Bot Auth (HTTP Message Signatures, `Signature-Agent` key directories of known agents) beside the published-range check of `waf/waf-ai-bots-ranges.md`: fetched in `pg_waf_refresh_ai_ranges()`'s job, never in `waf.php`; a verified agent is not rate-limited as an unknown bot, a failed signature is `spoofed`.

## Open questions for the architect review?
- ACP Bearer: accept `key:secret` of an application as Bearer on `/acp/*` only, or issue a separate ACP secret per application in `connect_apps`?
- MCP customer consent: customer token minted by a website consent screen (an `auth_tokens` row of kind `customer`), or an `api_devices` OAuth connection for members with storefront scopes only?

Related: plans/storefront-api-agentic-commerce.md, plans/storefront-api-routes-checkout.md
