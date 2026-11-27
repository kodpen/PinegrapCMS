---
name: office-sheet-format-controls
description: The sheet editor's format bar (OE3b) for the selected cells, its colour swatches, ranges waiting with the typed cells, and how each cell's look and number format is drawn
module: office
---

## What does the preview show of a cell's format?
- `pg_office_xlsx_read()` sends `looks` (style number => look) for the styles the shown cells use, and each row `s` (column => style) and `show` (column => the number as its format writes it). `cells` keeps the raw value the editor changes.
- A look (`pg_office_xlsx_style_looks()`, from the same read of the styles part as the date styles): `b`, `i`, `u` (any underline but none), `st` (strikethrough), `sz` (points, only one other than the first font's), `c` and `bg` (RRGGBB), `a` (left, center, right),
- also `w` (wrap text), `bd` (sides with a line, `l`, `r`, `t`, `b` in that order; diagonals left out), `n` (number, money, percent, date, other; general left out), with `d` decimals, `g` grouping, money's `y` symbol and `p` place.
- Only RGB colours are drawn; a theme or palette colour needs the theme part, which is not read, so the cell keeps the page's look.
- A coloured cell is drawn on paper: a text colour with no fill on white, a fill with no text colour under black (dark theme). A changed cell keeps its warning colour.
- `pg_office_xlsx_shown_number()` writes number, money and percent with the site's separators (`pg_number_separators()`). Dates were already shown as dates.

## How are the cells formatted?
- The bar above an editable grid (`.office-format`, look A), after the Undo and Redo pair (`office-sheet-undo.md`): Bold, Italic, Underline, Strikethrough, Size, the colours, Borders, three alignments (`pg-seg`), Wrap text, General / Number / Money / Percent / Date (`pg-seg`). Ctrl+B, I, U in the grid. The newer five: `office-sheet-bar-text-borders.md`.
- It changes the selected cells: one, or the rectangle Shift+click or Shift+arrows make from the first selected cell. Choosing the alignment already on gives back general.
- Each range waits in the workbook's one list beside the typed cells, in the order they were made (`{op: format, sheet, name, range, set}` in `view.edits()`, `office-sheet-change-list.md`), drawn on the cells at once (but the number text, shown after the save). Any number of ranges; at most 5000 cells a range. Each press of Undo takes back one part chosen, even where it joined a range already waiting.
- The bar shows over every sheet of an editable workbook; its format controls only on a sheet whose cells take a format (not protected, not sorted or filtered), the Undo and Redo pair always.
- Not offered on a protected sheet, even one whose protection allows formatting (`sheetFormattable()`). Money takes the site's base currency.

## How are text colour and fill chosen?
- Architect (2026-11-20, agent channel message 11221): a `pg-icon-button` each ("Text color", "Fill color"), the glyph over a 3 px bar in the selected cell's colour, opening the editor's own menu: "None" first, then a grid of preset swatches (aria-label the colour's name, the chosen one ringed). No native colour input and no free colour.
- Text: 000000, 6B7280, DC2626, EA580C, CA8A04, 16A34A, 0D9488, 2563EB, 7C3AED, DB2777. Fill: FFFF00, F3F4F6, FEE2E2, FFEDD5, FEF9C3, DCFCE7, CCFBF1, DBEAFE, EDE9FE, FCE7F3 (`TEXT_COLOURS`, `FILL_COLOURS`). File content, the same in both themes.
- A cell's colour from outside the list is kept and shown on the bar. The swatch rules sit in the editor's block of backend.src.css (`.office-swatches`, `.office-swatch`), not a look A recipe.
- Alignment as icon-only `pg-seg-item`s (aria-label, title), bold and italic as `pg-icon-button` (aria-pressed): the architect, same message.

## What was checked?
- `tests/office_cell_looks_test.php`: looks from a styles part (built-in and own number formats, theme colours left out), the shown numbers, a format save read back with `s`, `show` and `looks`. On the sandbox at 1440 and 390 px (PR #10871): every control applied, saved and read back; not opened in Excel or LibreOffice.
