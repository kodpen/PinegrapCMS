---
name: office-docx-table-bar
description: The document bar's Table button and size grid, Tab between cells, Insert or Delete row, column and table with the caret in a table (pg_office_view.js, DA5); what a save sends
module: office_docx_table
---

## Where are the table controls?
- "Table" (`bi-table`, after Link) opens a grid under it: ten columns, eight rows growing to one past the size pointed at (`pickRows()`, at most 20), the size above it ("3 columns × 2 rows"). A click, or the arrows and Enter, puts the table after the paragraph open or picked; Esc or Tab closes it.
- With the caret in a table (its nearest one), a group at the bar's end shows Insert row (below), Insert column (right), Delete row, Delete column, Delete table. At the end so nothing else moves as it comes and goes. Each press is one change of Undo.

## What do the keys do in a table?
- Tab goes to the next cell's first paragraph, Shift+Tab to the previous cell's last; Tab in the last cell puts a row in after it (one change). Shift+Tab in the first cell, and Tab in the last of a table that takes no row (merged cells, a new table at 20 rows), go on as outside a table (`office-docx-page-keys.md`).
- A cell's paragraphs are typed as any: Enter adds one in the cell, Backspace joins inside it.

## How does the page hold a table change?
- A new table is a `table.office-added-table` (data-after: the file's paragraph it follows; after a list item, drawn after its list) of `.office-new-row` / `.office-new-cell`, each cell one added paragraph with `data-like` ('' plain, else the file paragraph its cell copies, for its kind and alignment). Drawn at the text's width, as the writer saves it. A paragraph is drawn after it when none follows, as the writer adds one.
- Rows and columns put in a file table are new rows and cells the same way. A row, column or table of the file deleted is hidden with its paragraphs till the save; one put in simply goes. A file column deleted keeps a hidden cell in each new row.
- Rows and cells carry `data-r` / `data-c` names; Undo keeps each table's rows and cells (`capture()`, `restore()`).

## What does a save send?
- A new table: `{after, table: {rows, columns, cells}}` in reading order among the added paragraphs, cells as paragraph entries (an untouched empty one as none).
- A file table (`tableEntries()`, tested in Node): columns put in first (`column_after` from the file cell before, `column_before` from the first one), their cells by the file's rows; then rows the same way, cells by every column; then `row_delete`, `column_delete`; a table deleted only `delete`. The writer puts every insert in before anything goes, so a new row next to a deleted column sends that cell empty.
- Paragraphs gone with a deleted part send nothing; the count counts each table entry.

## What is refused on the page?
- A table in a new cell, row or table ("Save first"), after a deleted paragraph or one in `edit.shut`; rows and columns of a table with merged or uneven cells (`colSpan`, row lengths); deleting a part with a locked paragraph, a picture or a section break; a new table past 20 by 10; a file table past 63 columns. The writer's refusals (`office-docx-tables.md`) still stand on save.

## What was checked?
- `tests/office_docx_table_bar_test.php`: `tableEntries()` and `pickRows()` in Node, the limits against the writer's, the strings.
- Sandbox, Chromium, 1440 and 390 px. File Manager → New document: grid, a 3 by 2 table, typing with Tab and a row from the last cell, Insert column, Delete row, Undo and Redo, save; reopened: rows and columns put in and deleted in the file's table, a new row beside a deleted column, Delete table and its Undo, save. A channel's New document: grid by keyboard, Enter in a cell, Insert column, save. Each save read back with python-docx as shown. Not opened in Word.

Related: docs/memory/platform/office-docx-tables.md, docs/memory/platform/office-docx-page-keys.md
