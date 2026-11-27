---
name: office-docx-tables
description: Word tables put in after a paragraph, rows and columns put in or deleted, a table deleted (office_docx_table.php, DA4): the save entries, the new table's look and widths, the refusals
module: office_docx_table
---

## Which save entries change tables?
- In a save's `paragraphs` list (`office-docx-add-delete.md`): `{after: p, table: {rows, columns, cells?}}` puts a new table after paragraph p, 1 to 20 rows by 1 to 10 columns (`table_size`). Several entries after the same p (paragraphs and tables) read in the order listed.
- `{cell: p, table: action, cells?}` changes the table paragraph p is in (the nearest cell): `row_before`, `row_after`, `column_before`, `column_after`, `row_delete`, `column_delete`, `delete` (the whole table). p outside a table: `table_missing`.
- `cells`: a new table's rows of cells; a new row's cells by column; a new column's cells by row (the table as it stands then). A cell is a text or a list of paragraph entries `{text, format, align, kind}` as an added paragraph takes them; a cell not given stays one empty paragraph.

## In what order does a save write them?
- Texts, formats, alignments, then added paragraphs and tables (a paragraph is put after each new table that a paragraph does not already follow: a cell and the body end with one, and two tables side by side read as one), then table changes in the order listed (rows and columns put in first, then rows, columns and tables deleted), then paragraph deletes (one already gone with its row is passed over), then kinds.
- Several `row_after` from one cell read in the order listed; so do `column_after`. A table left with no row or no column is deleted whole.

## What does a new table look like?
- The document's "Table Grid" table style (found by its name in styles.xml) when it has one, else the style of the document's first styled table (never the default "Normal Table"), with Word's look (header row, first column, banded rows); with no such style, thin single borders (`sz` 4, `auto`) of its own. Strict documents get `start`/`end` borders.
- `tblW` auto; the columns share the text's width: the section's page width less its side margins and gutter (9026 twips when not written), in a cell the cell's width less 216. Each cell `tcW` in twips and one plain empty paragraph.

## How do a new row and column look?
- A row copies the row it is put beside: its row properties less the header mark and `cnfStyle`, each cell's properties and its first paragraph's kind (`pg_office_docx_paragraph_new()`).
- A column copies the cell of each row it is put beside and halves that column's width with it; a deleted column's width goes to the column before (the next for the first), so the table keeps its width.

## What is refused, and why?
- `table_merged`: a row or column change in a table with `gridSpan` over 1, `vMerge`, `hMerge`, `gridBefore`/`gridAfter` or rows of another cell count than its grid: its rows and columns are not the same cells end to end. Deleting such a table whole is allowed.
- `table_locked`: a tracked change anywhere in the table (`ins`, `del`, `cellIns`, `cellMerge`, `*Change`), a row or cell wrapped in a content control or custom XML, or the named paragraph in a content control or text box.
- `table_delete`: what would go holds a picture, field, comment, note, content control, tracked change or section break, or one end of a bookmark or permission range whose other end stays.
- `table_wide`: past Word's 63 columns. A table after a paragraph where none may be added: `add_locked`.

## What was checked?
- `tests/office_docx_table_test.php`: the entries, the new table's XML, widths, style choice, refusals, saves read back by `pg_office_read()` with the rest of the file byte for byte and the parts untouched, each refusal leaving the file as it was. Not opened in Word or LibreOffice. The bar's Table button, its grid and the row and column buttons: `office-docx-table-bar.md`.

Related: docs/memory/platform/office-docx-add-delete.md, docs/memory/platform/office-docx-page.md
