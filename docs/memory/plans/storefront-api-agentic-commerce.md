---
name: storefront-api-agentic-commerce
description: Plan (approved 2026-10-19, watch list): public storefront API (cart, checkout, customer auth) as the base for agentic commerce bindings (OpenAI ACP checkout_sessions, Google UCP manifest)
---

## Why?
- The external API covers products, orders (read/update/ship), customers and more, but has no order creation, cart, checkout, payment or customer sign-in; `/auth/login` is for team devices only; public session actions (`add_to_cart`, `get_shipping_methods`…) serve the site's own JavaScript. Agentic commerce needs exactly that layer: OpenAI's ACP asks sellers for five `checkout_sessions` REST endpoints with signed `order_created/updated` webhooks; Google's UCP asks for a `/.well-known/ucp` manifest, a Merchant Center feed and a PSP that accepts Google Pay tokens. Stripe's shared payment tokens are not offered in Türkiye yet, so this is a watch item, not wave 1.

## What is decided?
- Order of work: (1) `payment-driver-layer.md` first (a checkout API without SCA-capable drivers would re-expose raw card handling); (2) storefront API; (3) protocol bindings.
- Storefront API shape: new scope family `storefront:*` and a customer token (`/storefront/auth` with password, social or passkey → short-lived JWT-like token in `auth_tokens` with `kind='customer'`); routes `/storefront/cart` (create, add, update, remove, totals computed by the same server code the widgets use), `/storefront/checkout` (addresses, shipping methods, payment intent via driver, place order), `/storefront/customer` (profile, addresses, orders, returns). Express-order totals code is called, never copied (binding decision).
- ACP binding: `/acp/checkout_sessions` family mapping onto the storefront cart, `API-Version` header per spec, `Idempotency-Key` reuse, HMAC-signed webhooks through the existing outbound webhook client; delegated payment only where a driver supports a network token path.
- UCP: publish `/.well-known/ucp` only when checkout endpoints exist; capability `dev.ucp.shopping.checkout`, error vocabulary `out_of_stock`, `address_undeliverable`, recoverable `messages[]`; the feed is the product feed already built (`docs/memory/commerce/product-feed-serving.md`), and checkout eligibility in it is switched on here (`pg_product_feed_openai_row()`, `docs/memory/commerce/product-feed-decisions.md`).
- Web Bot Auth: the WAF learns to verify HTTP Message Signatures of known agent directories so verified shopping agents are not rate-limited as unknown bots (`docs/memory/waf/waf-ai-bots-ranges.md` pattern).
- MCP: the storefront routes become MCP tools under the customer's own consent, like today's routes.

## What remains?
- Design note with the route table and the token model (owner review) before any code.
- Then slices: auth (M), cart (M), checkout (L), customer (M), ACP (M), UCP manifest (S), WAF agent verification (S).

## Open questions?
- Guest checkout over the API without a customer token: yes, with a cart token; e-mail required.
- Which agent platforms matter for Turkish merchants first? Re-check each quarter; Trendyol/Hepsiburada remain the real channels.

## Where to start?
- Not before the payment driver layer ships. Then the design note; read `includes/api/schema.php`, `includes/panel/actions.php:124-135` and the decisions skill.
