---
name: etsy-connection
description: Etsy marketplace accounts: the etsy connect provider (PKCE, keystring, no secret on the token request, x-api-key on every call), the connector test, the shop read from the sign-in, five remote selects
module: etsy
---

## How does a site sign in to Etsy?
- Each site registers its own Etsy app (architect rule, as eBay): Connections › Etsy takes the keystring as Client ID and the shared secret as client secret; connect.php is the app's callback URL. Provider `includes/connect/providers/etsy.php`, built by `pg_etsy_provider()` in `includes/fn/etsy.php`; purpose `etsy` (`includes/connect/purposes/etsy.php`). No sandbox, so no second provider.
- Authorization code with PKCE (the connect layer always sends S256). Scopes `listings_r listings_w shops_r transactions_r transactions_w` (`pg_etsy_scopes()`), the ones ET2 to ET5 need. Access token one hour, refresh token 90 days.
- The token request sends `client_id` and the verifier, no secret: the provider says `'client_auth' => 'none'`, which `pg_connect_token_request()` honours like a public app.
- Every request, the token's included, carries `x-api-key: keystring:shared_secret` (Etsy since 9 February 2026). The provider names `'app_headers' => 'pg_etsy_app_headers'`; `pg_connect_app_headers()` adds it to token requests. `pg_connect_api()` does not add it: the connector's `mp_etsy_api_call()` puts it on each call.

## What does a marketplace account hold?
- `mp_providers()['etsy']` (`connectors/base.php`): no key, no text field; five remote selects `shop`, `shipping_profile`, `readiness_state` (Etsy's processing profile), `who_made`, `when_made`. One account is one shop, in the shop's currency (plan rule); no table or column.
- `who_made` and `when_made` are Etsy's fixed values (`pg_etsy_who_made_choices()`, `pg_etsy_when_made_choices()`), given as choices by the test so they sit with the others; their words are kept in the tester's language. Etsy moves the first `when_made` range (`2020_2026`) with the year: not verified against a live answer.
- The account's help says Etsy gives an app made in Turkey no buyer addresses and no shipment call; nothing is built apart for such a shop.

## What does the connector's test do?
- `mp_etsy_test_credentials($account, $call = null)` (`connectors/etsy.php`): with no connection, "Connect an Etsy account on Connections first." Calls `GET /users/me` (shop_id), `/shops/{id}` (name, currency: choice "name · USD"), `/shops/{id}/shipping-profiles`, `/shops/{id}/readiness-state-definitions`. No shop, no shipping profile or no processing profile each names what to create in Shop Manager.
- Errors read Etsy's `{"error": "…"}` (`pg_etsy_error_text()`); status 0, 429 and 5xx are retryable. `$call` is `function ($method, $path, $options)`; tests pass fixtures (`tests/fixtures/etsy/`, from Etsy's documentation).

## Where is it named?
- `pg_connections_registry()['etsy']` (group marketplaces, place `marketplace_settings.php`) covers the marketplace provider, the connect provider and the purpose; privacy inventory US with order data.
- Built in ET1 of `plans/sales-channels-global.md`. Tests: `tests/etsy_test.php`. Not verified against a live shop: the first try on a test shop is the owner's.
