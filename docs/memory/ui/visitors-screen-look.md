---
name: visitors-screen-look
description: How the Visitors overview is drawn: reference pictures, frames and blocks, one change column, folding lists, quiet dashes, anchors, calm charts, the menu's section links, the traps
---

## What should it look like?
- The pictures beside this note (sandbox data, "Last 30 days", comparison on) are the reference a change is held to (owner, 2026-11-04): `visitors-screen-look/wide-light.webp` (1440 px), `phone-dark.webp` (390 px), `menu.webp` (the flyout, light and dark). A PR that changes the look replaces them.

## What does a section look like?
- One frame (`pg_visitors_section()`): an icon tile (`pg_visitors_section_icons()`) and the title; nothing inside is framed again. A titled part is `pg_visitors_block($title, $body)`: a small heading in sentence case over its table, list, chart or line. Wide screens put two blocks side by side in `.pg-visitors-grid.is-split` (`.is-half` children; the rest span both).
- Tiles fit as many to a row as there is room for (two on a phone); a change is a small green or red pill; "No earlier figure" a quiet dash, "No change" a quiet 0, their words read out and shown on hover. A new site repeated them on every tile and row (owner, 2026-11-04: the screen was "too busy").
- Speed, AI visibility and site search use the same blocks, tables and charts (`pg_visitor_insight_tiles()`, `pg_visitor_insight_card()`, `pg_site_search_report()`); they had cards in cards, Title Case headings, inline SVG lines over a table of every day, and lists of 25 to 100 rows.

## How are lists and tables drawn?
- Compared figures take one column: `pg_visitors_change_heading()` ("Change vs. earlier", "Değişim"; the key "Change" is the verb) with `pg_visitors_change_cell($now, $before)`, the earlier count in the cell's title.
- A list shows `PG_VISITORS_LIST_ROWS` (5) rows, the pages table `PG_VISITORS_PAGES_ROWS` (10): `pg_visitors_table(..., array('limit' => n))` marks the rest `.pg-visitors-extra` and adds "Show all (n)" (`assets/js/pg_visitors.src.js`). `'hide_first' => true` hides the first heading but for screen readers when the title names the column.
- An empty top list is left out. Sources fold the UTM tags and codes under "Campaigns and codes" (`<details>`). Content's landing pages sit under the lists' grid, so a lone list takes the width.
- A second fact sits under the first as a quieter line (`.pg-visitors-cell-note`: a bot's kind), so a table fits a phone. On a phone the name column keeps 8.5rem and a wide table scrolls in its box.

## Which traps are behind it?
- The panel gives every `.table` `margin: 3rem 0 2rem` (list screens): `.pg-visitors .pg-visitors-table` resets it.
- A visually hidden `<caption>` keeps a pixel, enough for `.table-responsive` to draw a scroll bar: tables are named with `aria-label`.
- A visually hidden `<table>` keeps its content's width (the chart tables widened the phone page to 554 px): they sit in a `<div class="visually-hidden">`.
- `<main>` is `#content`, so the content section's anchor is `#content_section`: anchors come from `pg_visitors_anchor()`, the menu's and the alerts' too.

## How are the charts and the menu calmer?
- Every chart is `pg_visitors_chart()`: level x labels skipped to fit, no x grid or axis lines, five y ticks, points on hover only beyond 14 labels, the first solid line on a light area. Conversions: forms and orders on one axis, revenue in the tooltip. Speed: LCP in s over a dashed good limit, INP, CLS and views in the tooltip.
- The Visitors flyout lists the sections as `.pg-menu-anchor` (`includes/fn/output.php`, `backend.src.css`): no icon tile, 0.8125rem, muted, on a thin line down from Overview's tile (owner, 2026-11-04: they read as menu screens).
