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
- Look A (DL5b, #8921), the sheet's `table-page`: the root is `.pg-list-table.pg-card`, so the frame is the look's card (gradient, 16 px corners, card shadow); the rows sit on `--pg-surface-1` (`--pg-list-table-bg`), lines in `--pg-border-color-translucent`.
- Bar 48 px, the frame's header strip, the same height in both states (rows never jump): left the clear × (small icon button), the count (650, accent, tabular, a line after it) and the screen's bulk buttons (sized 28 px by the bar), tinted with the accent at 7% while rows are selected; right quick edit and columns as small icon buttons (`aria-label`, title), Details as a small ghost button with its name. Select all, Undo and the quick edit offers are small ghost buttons.
- Heading row 38 px, 12 / 550 in the third text colour, the sorted column's heading in the text colour with an 11 px arrow. Rows 52 px, cells 0 14 px; hover tints with `--pg-hover`, a selected row with the accent at `--pg-tint-select` (10% dark, 7% light); a sticky cell paints the tint over the surface. A thumbnail (`image` column) is 32 px with 9 px corners from 768 px.
- Every row is one line: a cell stops at `--pg-list-table-cell-max` (22rem) with an ellipsis (details and phone card show it whole); `.text-wrap` in a cell is held to one line from 768 px; the impact's view count follows its figure on the same line. The table is `width: max-content; min-width: 100%`, so narrow lists fill and wide ones scroll sideways.
- Row actions: the `actions` column's buttons are 28 px, 13 / 550, 8 px corners, at the row's right end. Foot: the loading line at the left in 13 px third text, "Show more" a small quiet button at the right. Empty list: 13 px third text, centred.
- From 768 px the first column (the name) sticks to the left edge and the `actions` column to the right edge while the rest scrolls (before, the edit button was cut off behind the scrollbar).
- SEO score (owner 2026-11-04: not under the record code): a narrow `seo` column beside the name (`pg_list_table_seo_column()`/`pg_list_table_seo_cell()`, `includes/fn/list_table.php`) with `pg_seo_render_bar()` (`.pg-seo-bar`); it sorts by the score where the view does, can be hidden from the columns menu, shows in the details panel and the phone card, and a fresh analysis redraws it (`data-pg-seo-bar`). Used by the products and pages lists; the old hidden badge column of the products list is gone.
- Width with `pg-measure-list`, taken by every root list screen (`view_*.php` but the form designer, `erp_*.php` lists, `mail_queue.php`): as wide as its columns, 1280 px to the window, centred, the name column taking the slack; the toolbar search is 300 px (`look-page-widths.md`). `tests/look_list_screens_test.php` pins both.

Related: docs/memory/ui/list-table-driver-browser.md
