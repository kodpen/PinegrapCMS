---
name: orders-list-redraw
description: Plan (owner 2026-11-22): All Orders redrawn: one bar, quick filters with counts, the figures as one strip with the charts behind Summary, rows with customer, three state pills and items
priority: 6
---

## What is asked?
- Owner (2026-11-22, in the architect's window), shown the View Order drawing: "bunu ve ürün sayfasını ve tüm siparişler ekranıyla birlikte güncelleyelim." The screen is All Orders (`view_orders.php`).

## Where is the drawing?
- `commerce/commerce-screens-drawing/orders.html` with `orders-dark.jpg` and `orders-light.jpg`: board A1 the screen at 1440 px, A2 Summary open and today beside the proposal. The drawing wins over these words, but for its Turkish.

## What is wrong today? (development 62b98310, seen on a sandbox)
- The list starts about 610 px down at a 1440 × 900 window: a bar of two rows, four figure cards 150 px high and two chart cards stand over it.
- A row does not say whether the order is paid or shipped. First Name and Last Name are two columns; five columns shown by default are mostly empty.

## Is it accepted?
- Yes. Owner (2026-11-22), shown the drawing: "onay" (`workflow/decisions-made-panel-15.md`).

## How is the screen put together? (board A1)
- One bar: Add Order, Export, Import Tracking Numbers, a rule, the period navigator, then at the right Sort, the status select, the type select and the filters button. Under 1280 px the period navigator takes the second row.
- Quick filters, a segmented control with counts: All, Awaiting payment, To ship, Shipped, Delivered.
- The figure strip: today's four figures as cells of one card, 64 px high, each sparkline at its value's right; "Summary" at the strip's end opens Top 5 products and Payment methods under it.
- The list on the table driver as today.

## Which columns? (mark 4)
- Default: Order Number, Order Date, Customer, Status, Payment, Shipping, Items, Total, Action.
- New: Customer (first and last name; the company when there is no name), Payment and Shipping (the pills of View Order's head, `commerce/order-screen-head.md`, from the same function), Items (the first line's product and "+N" for the others).
- First Name, Last Name, Type, User, Tracking Code, Member ID, Affiliate Code, Card Data and Profit stay in the column chooser, off by default. A person's saved columns are not changed.
- The new cells' data is read for the page's rows in one query each, as the amount cell's is.

## What do the quick filters list?
- Awaiting payment: today's rule (`pg_order_awaiting_payment()`). To ship: complete or exported, not awaiting payment, with an address, none of its addresses shipped. Shipped, Delivered: every address shipped, delivered, as the shipping pill says.
- They work with the period and every other filter; the counts are those of the period and the other filters. The choice is kept as the status select's is.
- The SQL of a filter and the pill's pure rule are held equal by one test over the same sample orders.

## What else is new?
- The change against the period before, of the same length, beside the two filtered figures.
- Summary is closed until opened; open or closed is the person's, kept as the list's columns are. The charts are read when it opens.

## What stays?
- Bulk actions, quick edit, the Details panel, exports, advanced filters, the sort menu, the phone cards, every filter's meaning. No schema.

## Which steps?
- OL1 (M): the bar, the strip, Summary; done (#11093). OL2 (M): the four new columns and the default set; done (#11142, `commerce/orders-list-columns.md`). OL2b (M): the Advanced Filters panel; done (#11167, `ui/advanced-filters-panel.md`). OL3 (M) after OL2: the quick filters, the change figures, close-out (`commerce/orders-list-*.md`, `ui/look-figure-cards.md` rewritten; this file deleted).
- Checked as `commerce/order-screen-redraw-rules.md` says, with at least thirty orders of every state in the period.

Related: docs/memory/commerce/order-screen-redraw-rules.md, docs/memory/commerce/order-screen-head.md, docs/memory/commerce/product-insights.md
