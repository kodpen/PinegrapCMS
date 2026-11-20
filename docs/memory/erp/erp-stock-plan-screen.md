---
name: erp-stock-plan-screen
description: Stock Planning screen (erp_stock_plan.php, SP3): filters, columns, dead stock value, settings card in config_text, the account card's Lead time, New purchase order per supplier by POST
---

## What does the screen show? (SP3 of plan stock-planning, P11, PR #10693)
- `erp_stock_plan.php`, Accounting menu "Stock Planning" after "Stock and cost"; `validate_erp_access($user)`, the read-only accountant sees it without the order buttons and the settings link.
- Rows: `erp_stock_plan_screen_rows()` reads the nightly cache joined to `products`, `erp_accounts.title` and `erp_product_costs.avg_cost`; dead stock and its value are worked out on read (`erp_stock_plan_dead()` with today's stock).
- Columns: product, stock, units a day (with "Season ×n" when not 1), days left, runs out, order by, to order, state (+ "Dead stock" pill and value), supplier ("No supplier yet"), lead time (Measured / From the account card). Printed list, sorted in the browser.
- Toolbar: "Minimum stock", the day the record began in the grow slot, a State filter (counts; Dead stock is a filter value) and a Supplier filter (`erp_stock_plan_filters()`, `erp_stock_plan_row_matches()`).
- Summary: counts of Runs out / Order now / Soon, the dead stock value over every planned product (products with no cost are counted apart), the three settings and a link to their card.

## Where are the settings? (P11)
- Card "Stock Planning": Site Settings › Accounting (`pgset-stock-plan`) for role < 3, `erp_settings.php#erp_stock_plan` for a role 3 user with the settings right, through `erp_settings_cards_refusals()` / `erp_settings_cards_save()` card `stock_plan` (`erp-settings-cards.md`).
- Stored as the config_text row `erp_stock_plan_settings`, JSON `{"safety","cover","dead"}`; no config column (`data/config-table-no-varchar.md`). Ranges: safety 0–365 (7), cover 0–365 (30), dead 1–3650 (90); an empty box is the default. A change is logged and reaches the plan at the next night's rebuild.
- Drawn only once `erp_stock_plan` exists.

## How does New purchase order work? (P12)
- The side card "Order per supplier" has one form per supplier with products in the shown list whose quantity is above 0 (`erp_stock_plan_supplier_orders()`): POST to `add_erp_purchase_order.php` with the token, `plan_fill=1`, `account_id` and `quantities[<product id>]`.
- That post only fills the form: the editor opens on the supplier with one line per product at the plan's quantity and the last purchase price (`erp_purchase_order_plan_quantities()`, `erp_purchase_order_rows_for_products($ids, $quantities)`); nothing is saved or sent until the person saves it. A form left in the session is replaced.
- The minimum stock screen keeps its GET `products[]`, quantities back to the minimum.

## The account card's Lead time?
- "Lead time (days)" on the account card (add and edit), shown once `erp_accounts.lead_days` exists; 0–365, empty or 0 is not set. It is P6's fallback after the measured median.

## What was not verified?
- No database and no browser run in the session that wrote it (`workflow/look-pictures-none.md`): the screen, the settings save and the filled order form were not opened.

Tests: `tests/erp_stock_plan_screen_test.php`, `tests/erp_account_form_test.php`.

Related: docs/memory/erp/erp-stock-plan-forecast.md, docs/memory/erp/erp-stock-days.md, docs/memory/erp/erp-settings-cards.md
