---
name: panel-money-base-currency
description: Panel amounts print in the base currency (prepare_amount()), never at the storefront currency the operator's browser picked; ERP documents in their own currency; Workspace and Marketplace helpers.
---

## How does the panel print an amount?
- A base-currency figure (prices, order totals, ERP account balances) under the base symbol, unconverted: `prepare_amount($minor / 100)` or `pg_format_money($minor / 100, BASE_CURRENCY_SYMBOL)`.
- Never `prepare_price_for_output()` / `pg_visitor_money()` in the panel: they multiply by `VISITOR_CURRENCY_EXCHANGE_RATE` and print `VISITOR_CURRENCY_SYMBOL`, which follow the shop currency the session picked (`init.php`, `update_currency.php`). An operator who switched the shop to EUR in the same browser read panel figures converted, under €.
- An amount stored in another currency (ERP invoice, receipt, incoming e-invoice, sales quote; an imported marketplace order) is printed in that currency, unconverted.

## Which helpers do it in the Workspace?
- `ws_money_out($minor)` (`includes/workspace/refs.php`): base amount, `prepare_amount()`, decoded plain text. Feeds the order, product and ERP account chips, watch `{{amount}}`, scheduled actions, the assistant, change cards and bulk changes. Until #PR it went through `prepare_price_for_output()`.
- `ws_erp_money_out($minor, $currency)`: an ERP document's amount in its own currency (`erp_money_out_currency()`), loading `includes/erp/money.php` alone when the ERP bootstrap has not run. The invoice (`erp_invoices.currency`), receipt (`erp_cash_transactions.currency`) and incoming e-invoice (`erp_edoc_inbox.currency`) chips use it; before, a 1,000 EUR invoice chip read "₺1.000,00" with ERP FX on. The sales quote chip calls `erp_money_out_currency()` directly (erp/erp-quotes.md).
- `ws_customer_money_text()` (customer summary) does the same split: workspace/workspace-customer-summary.md.

## And on the Marketplace screens?
- marketplace_products.php: the shop price sent to the marketplace (`mp_products_price()`) and the listed price, `pg_format_money(x / 100, BASE_CURRENCY_SYMBOL)`.
- marketplace_settings.php: an imported order's Total via `mp_settings_order_total()`. The import keeps the marketplace's currency in `orders.currency_code` with unconverted totals (`includes/api/outbound/orders.php`), so a code other than `BASE_CURRENCY_CODE` is printed after the figure without a symbol; the base currency under the base symbol, as view_order.php prints it.
- Pinned by tests/panel_money_base_currency_test.php.
