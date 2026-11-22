---
name: orders-list-figure-strip
description: All Orders (view_orders.php) one bar with the period navigator, the figure strip and Summary: closed until opened, read when it opens, open or closed kept per person in the browser
module: order_list
---

## How is the bar put together?
- One bar (`#button_bar`): Add Order, Export, Import Tracking Numbers, a rule (`.pg-orders-bar-rule`), the period navigator (`pg_period_nav()` with ` pg-orders-period`), then one group pushed right (`.pg-orders-bar-end`, no grow slot): Sort, the status select, the type select, the profit select when profit is on, the filters button.
- Under 1280 px the rule becomes a line break, so the navigator starts the second row with the group after it; nothing is moved with `order`, so Tab follows the rows (`admin-screen-toolbar-rules.md`).
- With the main menu expanded the bar is about 1,330 px wide: at a 1440 px window the group wraps to a second row at the right; the list starts at 247 px (was about 610).

## What is the figure strip?
- Today's four figures (Total Sales Amount Online, Total Sales Amount and Orders of the current filter, Refund Pending) as cells of one card, `.pg-figure-strip` (`ui/look-figure-cards.md`), each sparkline at its value's right. Refund Pending above zero is `.is-bad`.
- The board drew no sparkline in the strip; the plan's words keep them, and they were kept (OL1).

## How does Summary open?
- The button at the strip's end (`#pg_orders_summary_toggle`) opens a row under the strip with Top 5 products and Payment methods as bars in markup: name, bar, figure as text (`pg_order_list_summary_html()` in `includes/fn/order_list.php`).
- Closed until opened. Its markup is read the first time it opens, from `view_orders.php?summary=1`, over the list's own filters (`$orders_filtered_sql`); the page itself no longer runs those two queries.
- Open or closed is the person's, kept in the browser as the list's columns are: localStorage `pg.orders.<user id>.summary` = `1` when open. Storage that throws: the choice holds for the page. A failed read shows "The summary could not be loaded." and the next opening tries again.

## Where is it pinned?
- `tests/order_list_summary_test.php`; the old pins of the cards and charts were rewritten in `look_figure_cards_test.php`, `chart_text_equivalents_test.php`, `status_colour_words_test.php`, `panel_chart_colours_test.php`.

Related: docs/memory/plans/orders-list-redraw.md, docs/memory/ui/look-figure-cards.md
