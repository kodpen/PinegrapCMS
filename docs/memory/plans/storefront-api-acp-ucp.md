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
- Auth: ACP sends `Authorization: Bearer <value>`; the value is the application's own `key:secret`, accepted in that form on `/acp/*` only (`/api/` keeps Basic and OAuth). The ACP settings card tells the operator to create a dedicated server application for the platform and shows the value once (#7455, `workflow/decisions-made-product-35.md`).

## When does UCP come?
- `/.well-known/ucp` only once the checkout routes run in production: capability `dev.ucp.shopping.checkout` pointing at the storefront checkout, error words `out_of_stock` / `address_undeliverable` and recoverable `messages[]` already in place (`storefront-api-errors.md`).
- The feed is the existing one (`commerce/product-feed-serving.md`); checkout eligibility in `pg_product_feed_openai_row()` switches on in the same step (`commerce/product-feed-decisions.md`).

## How do storefront routes become MCP tools?
- Like every route (`api/api-mcp-server.md`): the rows are tools; cart and checkout tools for an OAuth connection holding `storefront:*`. Confirm and payment intent carry `'mcp' => false` until the customer's consent screen exists: an agent never places an order the shopper did not approve.
- The customer's consent is the member's own OAuth connection (#7456, `workflow/decisions-made-product-35.md`): the consent screen accepts a site member's sign-in through the website door, a member's ceiling is `storefront:*` only, and `api_storefront_shopper()` reads the shopper from a member-owned connection (a staff-owned one stays an application, shopper in headers). The member's account widget lists and ends their connections; no schema change.

## What does the WAF slice do?
- Verify Web Bot Auth (HTTP Message Signatures, `Signature-Agent` key directories of known agents) beside the published-range check of `waf/waf-ai-bots-ranges.md`: fetched in `pg_waf_refresh_ai_ranges()`'s job, never in `waf.php`; a verified agent is not rate-limited as an unknown bot, a failed signature is `spoofed`.

Related: plans/storefront-api-agentic-commerce.md, plans/storefront-api-routes-checkout.md
