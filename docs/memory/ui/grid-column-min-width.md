---
name: grid-column-min-width
description: CSS pitfall - a .row column taken off Bootstrap's width (width auto, max-width none) needs min-width 0, or a value that does not wrap widens the whole page past the window
---

## Why did edit screens grow wider than the window?
- Bootstrap gives a `.row` child `width` and `max-width: 100%`, which cap a flex item's automatic minimum size. A rule that sets `width: auto` or `max-width: none` (to size columns with `flex: 760 1 0%` instead) drops the cap, and the floor becomes the content's min-content width.
- A combobox value is `white-space: nowrap`, so on Edit Tax Zone and Edit Shipping Zone with many chosen states the form column, the row and the page grew to 3,000–17,000 px (look A's edit columns, #8934; fixed in the PR after it).

## What is the rule?
- A panel stylesheet rule whose selector ends on a direct `.row >` child and sets `width: auto` or `max-width: none` also sets a `min-width` (normally `0`).
- `tests/panel_grid_column_min_width_test.php` checks every stylesheet in `pg_look_stylesheets()` (`tools/look_rules.php`).
- The opposite trap, `overflow` zeroing a flex item's floor: `flex-overflow-hidden-pitfall.md`.
