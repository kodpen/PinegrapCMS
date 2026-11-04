---
name: visitors-screen-look
description: How the Visitors overview is drawn: frames and blocks, one change column, folding lists, quiet dashes, anchors, calm charts, the menu's section links, and the traps behind them
---

## What does a section look like?
- One frame (`pg_visitors_section()`): an icon tile (`pg_visitors_section_icons()`) and the title in normal case; inside it nothing is framed again. A titled part is `pg_visitors_block($title, $body)`: a small heading (`.pg-visitors-subtitle`) over its table, list or line. Wide screens put two blocks side by side in `.pg-visitors-grid.is-split` (`.is-half` children; the rest span both).
- Tiles fit as many to a row as there is room for (two on a phone); a change under a tile is a small pill, green or red, and "No earlier figure" or "No change" a quiet dash or "0 %" whose words are read out and shown on hover. A new site repeated them on every tile and row (owner, 2026-11-04: the screen was "too busy").
- Speed, AI visibility and site search draw with the same classes (`pg_visitor_insight_tiles()`, `pg_visitor_insight_card()` in `includes/fn/visitor_insights.php`, `pg_site_search_report()`); they had cards in cards with big uppercase headings.

## How are lists and tables drawn?
- Compared figures take one column: `pg_visitors_change_heading()` ("Change vs. earlier", "Değişim"; the key "Change" is the verb) with `pg_visitors_change_cell($now, $before)`, the earlier count in the cell's title. No "Previous period" column.
- A top list shows `PG_VISITORS_LIST_ROWS` (5) rows, the pages table `PG_VISITORS_PAGES_ROWS` (10): `pg_visitors_table(..., array('limit' => n))` marks the rest `.pg-visitors-extra` and adds "Show all (n)", toggled by `assets/js/pg_visitors.src.js`. `'hide_first' => true` keeps the first heading for screen readers only when the block's title names the column.
- An empty top list is left out. The sources section folds the UTM tags, tracking and affiliate codes under "Campaigns and codes" (`<details>`, `pg_visitors_sources_is_campaign()`). Content's landing pages sit under the lists' grid, so a lone list takes the width (auto-fit).

## Which traps are behind it?
- The panel gives every `.table` `margin: 3rem 0 2rem` (list screens): `.pg-visitors .pg-visitors-table` resets it.
- A visually hidden `<caption>` keeps a pixel (Bootstrap leaves captions static), enough for `.table-responsive` to draw a vertical scroll bar: tables are named with `aria-label`.
- A visually hidden `<table>` keeps its content's width: the charts' figure tables widened the phone page to 554 px. They sit in a `<div class="visually-hidden">`.
- `<main>` is `#content` (the skip link's target), so the content section's anchor is `#content_section`: every anchor comes from `pg_visitors_anchor()` (`includes/fn/visitor_periods.php`), the menu's and the alerts' too.

## How are the charts and the menu calmer?
- Level x labels skipped to fit, no x grid, no axis lines, five y ticks; points only on hover beyond 14 labels; the first solid line on a light area of its colour; legend boxes small and to the start.
- The Visitors flyout lists the sections as `.pg-menu-anchor` (`includes/fn/output.php`, `backend.src.css`): no icon tile, 0.8125rem, muted, on a thin line down from the Overview entry's tile. They read as menu screens before (owner, 2026-11-04).
