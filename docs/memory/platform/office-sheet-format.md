---
name: office-sheet-format
description: Format of selected cells of an .xlsx (office_format.php, OE3a): the save entry, how new styles are written beside the old ones, the number formats, what is refused and why
module: office_format
---

## How is the format of selected cells changed?
- A save whose `cells` list holds one entry `{op: format, sheet, name, range: "B2:D5", set: {...}}`, sent to the endpoints cell edits use (`explorer_file_save`, `ws_file_office_save`). `pg_office_xlsx_save()` hands it to `pg_office_xlsx_format_save()`, under the same lock, version check and package rebuild. Mixed with other changes it is refused (`none`). No new endpoint, permission or schema.
- `set` holds one or more of: `bold`, `italic` (true/false), `color`, `fill` (`RRGGBB` or `#RRGGBB`; `""` takes it off), `align` (`""` general, `left`, `center`, `right`), `number` (`general`, `number`, `money`, `percent`, `date`) with an optional `symbol` for money. Anything else: `none`.
- At most 5000 cells a save (`PG_OFFICE_EDIT_MAX_CHANGES`, `many`), columns inside the grid's 200.

## How are the styles written?
- A style is never changed in place (other cells share it). For each style the range uses, `pg_office_format_styles()` copies its `cellXfs` entry, applies the set and adds the copy after the others; its border, protection, cell style and other alignment (wrap, vertical) are kept. The font or fill it needs is a copy of its own with the change, added after the others.
- An existing font, fill or cell format equal to the one needed is used instead of a new one (compared by name, attributes and children, not by prefix or spacing). So taking off what is not there gives back the cell's own style.
- `apply…="1"` is set only on what changed. Counts are updated; nothing else in the styles part changes.
- Number formats: general 0, number 4 (`#,##0.00`), percent 10 (`0.00%`), date 14 (the reader's built-in date), money a custom `#,##0.00\ "₺"` from 164 up (symbol from the entry, else the site's base currency; quotes and backslashes taken out), reused when the part has it already.
- The styles part is the one the relationships name (`pg_sheet_xlsx_part()`, as the reader since #10765).

## What happens to the cells?
- `pg_office_xlsx_format_sheet()` rewrites only the rows of the range; a cell gets `s` of its new style (`s` dropped for 0). A cell or row the range covers and the sheet lacks is made, with no value, from the row's, the column's or the default style, and only when its new style is not 0. `spans` is widened.
- Values and formula results stay: no `fullCalcOnLoad`, no other worksheet written. Only the sheet and the styles part change.

## What is refused?
- A signed file, a password to change it (as cell saves), a chart sheet, a sheet renamed since (`changed`).
- A protected sheet unless its `sheetProtection` has `formatCells="0"` (`format_protected`); no or unreadable styles part (`format_styles`); a styles part over 8 MB (`format_large`). Messages: `pg_office_format_message()`.

## What was checked?
- `tests/office_format_test.php`: the request, the money code, the style writer (reuse, new fonts/fills/formats, existing entries untouched), the sheet splice with new cells and rows, a save read back by `pg_office_read()` (a number shown as a date) with untouched parts compared byte for byte, every refusal. Not opened in Excel or LibreOffice.

## Where are the editor's controls?
- `office-sheet-format-controls.md` (OE3b): the format bar and the preview drawing each cell's look.
