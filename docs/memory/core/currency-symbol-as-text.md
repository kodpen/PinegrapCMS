---
name: currency-symbol-as-text
description: A stored currency symbol is HTML (&#8378;): raw into HTML, decoded (pg_money_text(), html_entity_decode()) before h(), .text(), a canvas or a script; fixed spots, the guard test
---

## What does the currencies table hold?
- `currencies.symbol` is HTML for the pages that print it: the starter data has `&#8378;` (TRY), `&euro;`, `&pound;`, `&yen;`, `$`; an API write stores the text escaped (`api_store_currency_symbol_stored()`). `init.php` defines `BASE_CURRENCY_SYMBOL` and `VISITOR_CURRENCY_SYMBOL` from it as stored.
- The stored value stays as it is: site output prints it raw.

## How is it printed?
- Into HTML as markup: raw, as `pg_format_money()` and `prepare_amount()` return it (their result is HTML).
- Into text that is escaped or drawn later (`h()`, jQuery `.text()`, `textContent`, a Chart.js canvas, a plain-text mail, a PDF, a log line): decoded first. Money: `pg_money_text($amount, $symbol)`; the symbol alone: `html_entity_decode($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8')`. `h()` after that still shows a symbol typed as markup as text.
- Never `h(pg_format_money(…))`, `h(prepare_amount(…))` or `h(BASE_CURRENCY_SYMBOL)`: the entity is escaped twice and the screen reads `&#8378;0,00`.
- A decoded symbol printed into a `<script>` goes through `json_encode(…, JSON_HEX_TAG | JSON_HEX_AMP)`, so no `<` in it can end the element.
- `pg_money_text()` decodes only a symbol that starts with `&`.

## Where was it fixed?
- #8391, all showed `&#8378;`: Edit User › Store Credit, the balance and the cards' amount and balance (`pg_credit_staff_money()`, `includes/fn/store_credit_staff.php`); the container form's Summary cost (`data-symbol`, written with `.text()`, `includes/forms/container_form.php`); the orders list's total sparkline tooltip (`$orders_chart_data['currency']`, `view_orders.php`).
- Earlier: the API (`api_currency_symbol_text()`, #1010, `api/api-currency-symbol.md`), the new dashboard cards (`pg_dashboard_v2_money()`, #6359, `ui/dashboard-v2-shop-cards.md`), Site Log lines (`pg_order_log_currency_symbol()`), the Workspace (`ws_money_out()`).

## What holds it?
- `tests/panel_currency_symbol_text_test.php` reads every product file (`data/` and `includes/local/` left out) for `h()` around `pg_format_money(`, `pg_visitor_money(`, `prepare_price_for_output(`, `prepare_amount(` or a stored symbol constant, and pins the container Summary and the orders chart.
- `tests/store_credit_staff_test.php`: the card's money (`&#8378;`, `&euro;`, `$`, a symbol typed as markup).
