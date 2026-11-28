---
name: order-currency-screens
description: How an order's screens, e-mails, list, report and API print the currency the order is kept in, and which sums stay in base
module: order_currency
---

## How does a panel screen print one order?
- View Order (with its refund, cancel and payment-plan texts and the refund form's symbol) and print_order.php take `pg_order_money_symbol($row)`: symbol and escaped suffix for `pg_format_money()`. Gift card balances stay base. The Currency row shows the charged currency even without multi-currency.
- The refund log line names `pg_order_amount_currency()`. Stripe refunds in the order's currency (`market-payment-methods.md`); a driver without `order_currency` is not offered to such an order, so it never refunds one (MK8 brings PayPal, PayTR, iyzico).

## How does the site print an order?
- The receipt, preview and plain-text receipt (their `get_*_content()` bodies) and the HTML receipt e-mail (`order_completion.php`) run inside `pg_order_display_begin($order_id)` / `pg_order_display_end($before)`.
- While an order in another currency than the base is drawn, `prepare_price_for_output()`, `pg_visitor_amount()` and `pg_visitor_money()` use rate 1 and its symbol plus ` CODE` (`pg_visitor_currency_parts()`), and `pg_order_display_converts()` is false, so the "(base total) *" note is left out.
- An order in base keeps the visitor's display currency, as before; one with `market_id` in the base currency prints as it is (rate 1, base symbol), never rounded again by the market (`market-cart.md`).
- The order view widget, order_invoice_print.php and the account order history call `pg_order_money()` for such an order.

## What stays in base?
- Sums over orders: the orders list's filter total, strip, sparkline, change figures and its Online / To refund cards read `pg_order_base_sql()`; a row's amount cell prints its own currency (`pg_order_list_amount_html(..., $row)`).
- The Order Report adds orders up, so `pg_order_report_order()` converts each order's figures with `pg_order_base_amount()` (0 when the rate is not known) and prints the base symbol.

## What does the API give?
- An order carries `currency`: `pg_order_amount_currency()`, the charged currency, else the base one. `api_order_select()` reads `charged_currency` when the site has it.
- Pinned by tests/order_currency_test.php and tests/api_order_present_test.php.
