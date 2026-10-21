---
name: api-currency-symbol
description: External API currency symbol is plain text (₺, €), not the HTML entity the currency screen stores (&#8378;, &euro;): one rule, api_currency_symbol_text(), for GET /meta and GET /store/currencies
---

## Where does the symbol come from?
- The currency screen keeps `currencies.symbol` as HTML for the pages that print it: the starter data has `&#8378;` (TRY), `&euro;`, `&pound;`, `&yen;`, `$`. `init.php` defines `BASE_CURRENCY_SYMBOL` from the base currency's row as it is stored.

## What does the API give out?
- Plain text: `api_currency_symbol_text($symbol)` (`includes/api/resources/store.php`) is `html_entity_decode(…, ENT_QUOTES | ENT_HTML5, 'UTF-8')`. `GET /store/currencies` (`api_store_currency_present()`) and `GET /meta` (`ecommerce.currency.symbol`, `includes/api/resources/meta.php`) both go through it, so the same field reads the same in both answers.
- Before PR #… (issues #909, #918) `GET /meta` handed out `BASE_CURRENCY_SYMBOL` as stored: `"symbol":"&#8378;"`, and a client drawing a price with it showed "&#8378;19,99"; only `$` shops did not notice.

## What else should know?
- A new API field that carries a currency symbol (an order's, a visitor currency's) goes through the same function. `meta.php` calls it from `store.php`; both are loaded by the API bootstrap and by the OpenAPI loader.
- Tested in `tests/api_currency_symbol_test.php`.
