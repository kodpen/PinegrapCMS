---
name: area-tab-strip-centred
description: An area's tab strip sits centred in the window on every tab (owner 2026-11-19, poll 153): the shared class .pg-area-tabs, the screens that have it, the strips that are not area strips
module: messages_area
---

## Where does an area's tab strip sit?
- Centred in the window's content area on every tab, whatever width the tab's screen takes (owner, 2026-11-19, poll 153, `workflow/decisions-made-panel-10.md`); the lists' and edit screens' widths stay. Before, the strip sat at `#content`'s left edge, so it jumped between a full-width tab and a centred list.
- How: one shared class, `.pg-area-tabs` on the strip's `<nav>` (TS2): a flex row whose `.pg-seg` has `margin-inline: auto`. A screen's own class only spaces its strip (margin-bottom), never places it. Every tab screen's `#content` is centred (full width, or the list measure's fit-content box), so centred in `#content` is centred in the window. Auto margins, not `justify-content: center`, keep a strip wider than a phone scrollable from its first tab; under 576 px the strip fills the line.
- A tab screen prints the strip first in `#content`, notices under it, so a notice never moves it down.
- `tests/look_page_widths_test.php` and `tests/messages_area_test.php` pin it.

## Which areas have it?
- Communication (`.pg-area-tabs pg-messages-tabs`, `pg_messages_tab_strip()`): its six tabs, full width or the list measure (TS1, #10390; the shared class since TS2).
- The marketplace products screen's views (store area, `marketplace_products.php`, list measure): Catalogue on the marketplace, Products in this shop, Listings, each with its own columns (TS2). Its `#mpp-flash` messages come under the strip.
- A new strip of links over a group of screens takes `.pg-area-tabs`, printed first in `#content`, on screens whose `#content` is centred (no edit measure, no rail).

## Which `.pg-seg` strips are not area strips? (inventory of 2026-11-19, TS2)
- The ERP has none: its screens open from the menu; Cash flow's two views (`erp_cashflow.php`) sit inside its tools row.
- The ready reports strip of the order and visitor reports (`.pg-report-strip`) sits under the report's toolbar with its label, as part of the report's tools, and stays left with the period row.
- Filters and view switches inside a toolbar or card (`pg_list_seg_filters()`, Site Log, Connections, reports' period, course view, Workspace tables, the marketplace lists' filters) are tools, not tabs.

Related: docs/memory/ui/look-page-widths.md, docs/memory/ui/messages-area-shell.md
