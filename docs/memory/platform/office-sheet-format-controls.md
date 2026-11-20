---
name: office-sheet-format-controls
description: The sheet editor's format bar (OE3b) for the selected cells, one format change a save, and how the preview draws each cell's look and number format
module: office
---

## What does the preview show of a cell's format?
- `pg_office_xlsx_read()` sends `looks` (style number => look) for the styles the shown cells use, and each row `s` (column => style) and `show` (column => the number as its format writes it). `cells` keeps the raw value the editor changes.
- A look (`pg_office_xlsx_style_looks()`, from the same read of the styles part as the date styles): `b`, `i`, `c` and `bg` (RRGGBB), `a` (left, center, right), `n` (number, money, percent, date, other; general left out), with `d` decimals, `g` grouping, money's `y` symbol and `p` place.
- Only RGB colours are drawn; a theme or palette colour needs the theme part, which is not read, so the cell keeps the page's look.
- A coloured cell is drawn on paper: a text colour with no fill on white, a fill with no text colour under black, so it is not lost in the dark theme. A changed cell keeps its warning colour.
- `pg_office_xlsx_shown_number()` writes number, money and percent with the site's separators (`pg_number_separators()`). Dates were already shown as dates.

## How are the cells formatted?
- The bar above an editable grid (`.office-format`, look A: `pg-icon-button` Bold and Italic, a `pg-seg` of three alignments, a `pg-seg` General / Number / Money / Percent / Date, a quiet Undo). Ctrl+B and Ctrl+I in the grid too.
- It changes the selected cells: one, or the rectangle Shift+click or Shift+arrows make from the first selected cell. Choosing the alignment already on gives back general.
- The change waits as one save of its own (`{op: format, sheet, name, range, set}` in `view.edits()`), drawn on the cells at once (but the number text, shown after the save). One range a save; nothing beside cell, row, column or sheet changes; at most 5000 cells. Escape or Undo drops it.
- Not offered on a protected sheet, even one whose protection allows formatting.
- Money takes the site's base currency (the server's default).

## What is not there yet?
- Text colour and fill: the Design examples screen has no colour field, so they wait for the architect (PR #10871's decision question). The server takes them already (`office-sheet-format.md`).

## What was checked?
- `tests/office_cell_looks_test.php`: looks from a styles part (built-in and own number formats, theme colours left out), the shown numbers, a format save read back with `s`, `show` and `looks`. The bar was tried headless on a made-up workbook; not in a browser on a site.
