---
name: market-site-prices
description: The visitor's market on the site (MK5): chosen by country or the country list, its currency and rounding in every printed price, the currency picker hidden, the page cache kept per country
module: markets_site
---

## How is the visitor's market chosen?
- `pg_market_visitor()` (`includes/fn/markets_site.php`, loaded by `markets.php`): the country chosen in the country list (`$_SESSION['ecommerce']['market_country']`, '' = "Other countries"), else `pg_geo_country_code()` (T1, Tor, is no country). The enabled market holding that country (`pg_market_site_markets()`, one query a request) is the market; a country in no market sees the base currency.
- Only enabled markets count. A site with none (`pg_markets_on()` false) is exactly as before: the currency picker, `update_currency.php` and the session's `currency_id` work as they did.

## What does init.php define?
- On a site with markets the market's currency replaces the picked one: `VISITOR_CURRENCY_*` from the `currencies` row of the market's code through `pg_visitor_currency_row()` (no row or a rate not above 0: base, and no market).
- `VISITOR_MARKET_ID` (0 none) and `VISITOR_MARKET_ROUNDING`; a market in the base currency keeps the base constants and still rounds.

## How is a price converted and rounded?
- `pg_market_price_minor($base_minor, $rate, $rule)`: base minor units times `currencies.exchange_rate` (the day's rate), `round()` to minor units, then `pg_market_round_minor()`: `whole` up to whole units, `end99` up to the next .99 (13.00 becomes 13.99), a negative amount by its size, 0 stays 0.
- `pg_visitor_price($major)` is the printed price: market rule inside a market, `pg_visitor_amount()` outside. `prepare_price_for_output()`, `pg_visitor_money()`, `pg_visitor_money_text()`, the collection reference price and the entity money field go through it.
- `pg_visitor_amount()` stays the bare rate: a typed donation is divided back by it and the price filter of the catalog divides by it; never round there.
- The classic product page's attribute script gets prices already in the market (`pg_market_js_prices()`) and rate 1 (`pg_market_js_rate()`): `frontend.min.js` is hand-patched, so the script is not changed.

## What about the cart?
- The cart is worked in the market's currency and printed as it is: `market-cart.md`.

## Where is the country list?
- `pg_market_country_form($send_to, 'classic'|'cart')`: the enabled markets' countries by name, then "Other countries"; posts to `update_market.php`, which keeps a country a market holds (any other value is ''), sets an incomplete cart order's `currency_code` to the market's currency, and returns to the page.
- It replaces `get_update_currency_form()` (classic pages) and the cart widget's `__currency_selector`. Every other currency picker is hidden through `pg_currency_choice_shown()` (`ECOMMERCE_MULTICURRENCY` and no market): the classic `$currency_system` blocks and `pg_sw_currency_rows()` (designer widgets' `has_currency_choice`).

## How is the page cache kept?
- `pg_market_cache_note()` runs with any printed visitor money (`pg_visitor_currency_parts()`, `pg_visitor_price()`, `pg_market_js_prices()`): on a site with markets and a visitor priced by the country they are in, the page adds `country` to `pg_audience_cache_vary()`, so the file cache keeps one copy per country and no shared cache keeps it (`designer/audience-conditions.md`).
- Kept per country, not per market: the router knows the country without a query, and a country is in one market, so every copy is right. A country chosen in the list is in the session, which bypasses the cache (`platform/page-cache-gate.md`).
- Saving a market is a signed-in panel POST: the kept pages are dropped.

## Tests?
- `tests/markets_site_test.php`: the rounding rules, the conversion, the market of a country, the visitor's country, the list's choices, the wiring.
