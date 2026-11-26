---
name: bug-hunt-product-insights
description: Bug hunt of Edit Product's Performance card (#11138; architect, 2026-11-26, K3; #11815): five faults fixed, no issue opened, what was read clean
module: product_insights
---

## What was the area?
- Edit Product's Performance card: `includes/fn/product_insights.php`, the `product_insights` action (`pg_panel_product_insights()`, `includes/panel/commerce.php`), `assets/js/product_insights.js`. Named by the architect on 2026-11-26 for coordinator K3. Figures: `commerce/product-insights.md`.

## What did it fix? (its PR lists each with file and line)
- An order refunded in full, or one with `cancelled_at` set, still counted in Units sold, Revenue, Orders, Last sale, Returns and Margin. The sales lines now follow `pg_customer_value_order_counts()` in SQL (`pg_product_insights_counted_sql()`), as the Customers report and Recovered carts count.
- A return request the customer withdrew before sending anything (closed with no shipped, received or refunded stamp) counted in Returns. Returns now count what `pg_return_holds_goods()` holds (`pg_product_insights_returns_held_sql()`).
- Revenue added a marketplace order's amounts in its own currency (EUR cents beside TRY kuruş) under the store's symbol. Revenue now sums only orders in the base unit (`pg_product_insights_base_unit_sql()`, the `pg_customer_value_order_unit()` rule); units and orders still count every order.
- Try again asked for the stored period, always 30 days when browser storage is blocked, while 12 months or All stayed pressed. It now asks for the period on screen.
- After one refusal with a server message, every later failure on the card (a lost connection too) showed that old message. The message now goes to that failure only.

## What was opened as an issue?
- None.

## What was read clean?
- A partly refunded order counts in full: Revenue is price times quantity as the line shows it (`product-insights.md`); a partial refund names no line, and a return of the product shows under Returns.
- A bundle's parts: a bundle is one line of the bundle product, so a part sells only by its own line (documented). Variants are products of their own; a set's total was left out by design.
- Prices are integer minor units summed as `SIGNED` in SQL; percents use `round()`.
- Views: 0 views shows Conversion "—"; the rollup keys by product id, so a rename or a move keeps the views; tracking off or a period before the rollup's first day leaves Views and Conversion out; a period before the product existed simply has no rows.
- Margin: no product cost leaves it out; a cost changed inside the period is read per order from `erp_order_profit`, as written then.
- Left carts: `COUNT(DISTINCT orders.id)`, so a cart with two lines of the product counts once; a removed line is deleted, so removed and added again is one line.
- Periods: day and month starts from `strtotime()` in PHP's (the site's) time zone, a clock change included (tests); the views' dates in the same zone.
- The ten-minute keep: per session, keyed by product, period and margin right, so a person without the margin right never gets a kept margin; a sale or refund shows within ten minutes, as the foot says. The action takes Edit Product's own gate before any kept read; every product is open to whoever passes it.
