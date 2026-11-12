---
name: list-table-layout
description: How a list on the table driver looks (backend.src.css): the frame, one-line rows, sticky name and actions columns, the selection bar, the details panel's layout rule, the SEO score column
module: list_table
---

## What do the details panel and the phone card show?
- Details panel (`aside.pg-list-table-details`): every column of the current row (last clicked, or the keyboard's), hidden ones too, in the user's order, from the cells' markup. Beside the rows (20rem) from 1024 px, a fixed side sheet below.
- Below 768 px each row is a card: the heading is visually hidden, each shown cell led by its `data-pg-label` (set on every cell); a cell's `text-center`/`text-end` still aligns it.

## What layout rule does the details panel follow?
- `ui/details-panel-rule.md`: name first, pictures, label/value lines with a fixed label column, buttons last, empty values left out. The phone card uses the same label width.

## How does the list look?
- One framed surface (border, large radius, card background): the bar is its header strip, rows and details its body.
- The selection bar is 3rem high in both states (rows never jump): left the clear ×, the count (bold, primary, a divider after it) and the screen's bulk buttons (sized small by the bar, as the File Manager's bar), tinted while rows are selected; right the quick edit, columns and details tools.
- Every row is one line: a cell stops at `--pg-list-table-cell-max` (22rem) with an ellipsis (details and phone card show it whole); `.text-wrap` in a cell is held to one line from 768 px; the impact's view count follows its figure on the same line. Headings are small, semibold, muted. The table is `width: max-content; min-width: 100%`, so narrow lists fill and wide ones scroll sideways.
- From 768 px the first column (the name) sticks to the left edge and the `actions` column to the right edge while the rest scrolls (before, the edit button was cut off behind the scrollbar). Sticky and selected cells use opaque backgrounds (`color-mix(…, var(--pg-card-bg))`).
- SEO score (owner 2026-11-04: not under the record code): a narrow `seo` column beside the name (`pg_list_table_seo_column()`/`pg_list_table_seo_cell()`, `includes/fn/list_table.php`) with `pg_seo_render_bar()` (`.pg-seo-bar`); it sorts by the score where the view does, can be hidden from the columns menu, shows in the details panel and the phone card, and a fresh analysis redraws it (`data-pg-seo-bar`). Used by the products and pages lists; the old hidden badge column of the products list is gone.
- Width with `pg-measure-list`: as wide as its columns, 1280 px to the window, centred, the name column taking the slack (`look-page-widths.md`).

Related: docs/memory/ui/list-table-driver-browser.md
