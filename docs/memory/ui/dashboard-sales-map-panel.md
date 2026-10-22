---
name: dashboard-sales-map-panel
description: Dashboard sales map widget 1 panel behavior: corner menu with fixed Popper, card colour, permission gates, link gating, brief line signals, reused id 1.
---

## How are the switches and colours built?
- Switches sit in a menu at the map's corner (`.pg-smap-menu`), with no strip below: the strip stole panel height, and `.btn-group > .btn` `flex: 1 1 auto` made each button take a third of the panel and wrap its label onto two lines.
- The menu is built with Popper **`strategy: "fixed"`**: the box is inside a scroll box and an absolutely positioned menu would be clipped there. The selected row is marked with a **tick**, not colour.
- Card colour is `.widget[widget-id="1"]`: teal (`#0d9488`), one step from the stores' green. The map ramp is tied to `--pg-accent` (`color-mix`), so changing the card colour changes the map too.

## Which permissions gate it?
- The gate is `USER_MANAGE_ECOMMERCE_REPORTS`, **not** `USER_MANAGE_ECOMMERCE`: the card is a sales report, and the two permissions are separate on the user (`validate_ecommerce_report_access()` vs `validate_ecommerce_access()`). Roles 0-2 carry both unconditionally; for role 3 the operator-given flag decides.
- Links pass a separate gate: every link goes to `view_orders.php`, which needs `manage_ecommerce`. A user who can read the report but not manage orders gets rows drawn without links (`pg_widget_row` produces an href-less div) and the canvas gets `is-static`, so the cursor does not point to somewhere it will not go.

## How does the welcome brief line (`pg-brief-line`) use this data?
- `welcome.php` signal block has two fields: `map_lead` (busiest place of the week) and `map_new` (place that ordered this week, not in the previous three weeks): one query, two windows. `map_new` also **requires other orders in the previous three weeks**, otherwise every place is "new" in a store's first week. Name resolution is shared with the card (`pg_sales_map_label()`).
- The two halves of the line pass two gates: the signal is collected with `USER_MANAGE_ECOMMERCE_REPORTS`; the suggestion half is **not produced at all** without `manage_ecommerce` (its value is the order-screen link). The reading half is wholly escaped with `h()`, so no link is placed there - the place name is plain text.

## Is it refreshed, and why does it have id 1?
- The widget is NOT in the per-minute refresh list in `welcome.php` (full table scan, once on first load).
- id 1 was a retired slot and was deliberately reused; the other retired ids stay retired (`dashboard-widget-files.md`). Rationale and trade-off are in the `welcome.php` registry comment.
