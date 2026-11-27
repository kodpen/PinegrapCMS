---
name: office-sheet-bar-text-borders
description: The workbook format bar's Underline, Strikethrough, Size, Borders and Wrap text (SA2): the controls, the state they show, how the grid draws each, the words' own function
module: office_format
---

## Which controls are they?
- Underline and Strikethrough: `pg-icon-button`s after Italic, pressed while the first selected cell has it; Ctrl+U toggles underline in the grid, as Ctrl+B and Ctrl+I.
- Size: a select copied from the document bar's paragraph style (`pg-input pg-input-sm`), 8 to 72 points (`FONT_SIZES`: 8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 36, 48, 72). A cell with no size of its own shows "Default"; a size outside the list shows its number; neither is a choice.
- Borders: a `pg-icon-button` after the colours opening the editor's menu with No borders, All borders, Outside borders, Bottom border; pressed while the first selected cell has a line on any side.
- Wrap text: a `pg-icon-button` after the alignments, pressed while the cell wraps.
- Each joins the change list as a format part (`office-sheet-change-list.md`); the save sends `underline`, `strike`, `size`, `border`, `wrap` (`office-sheet-format.md`).

## How does the grid draw them?
- `paint()`: underline and line-through as `text-decoration-line`, wrap as `white-space: pre-wrap`, a size as `em` of the grid's text taken as 11 points (`BASE_SIZE`): the reader sends a size only when it is not the first font's and does not send that font's size.
- A border's sides are the cell's `office-bd-l/-r/-t/-b` classes, drawn by a `::after` line just inside the cell (cells clip what passes their edge), in `--pg-fg-emphasis`, black on a cell drawn on paper (`color-scheme: light`).
- `listBook()` turns a range's border into each cell's `sides` (`borderSides()`, the twin of `pg_office_format_border_sides()`), so an outline draws only the edges; `bd` keeps l, r, t, b order.
- The view-only preview draws the same looks (the reader sent `u`, `st`, `sz`, `w`, `bd` already).

## Where are the words?
- `pg_office_format_view_strings()` in `includes/fn/office_format.php`, added to `pg_office_view_strings()` where the script gets its words (`view_folders.php`, `includes/workspace/screen.php`).

## What was checked?
- `tests/office_sheet_list_test.php` (`test_office_sheet_list_new_formats`): each border's sides, the new parts laid on a look and taken off, an outlined range's cells, the entry sent as chosen.
