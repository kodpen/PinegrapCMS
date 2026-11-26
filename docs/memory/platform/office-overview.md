---
name: office-overview
description: Start file for the office-* files: Office files shown and edited by own code in the File Manager and channels; decisions, parts by screen label, which file holds what
module: office
---

## What is it?
- Office files opened in the File Manager (Quick Look, the file window) and a channel's Preview, read and written on the server by the product's own code, drawn by `assets/js/pg_office_view.js`: the same view and controls in both places.
- Readers: `includes/fn/office.php` (`pg_office_read()` for every kind; .ods and .xls through the sheet reader, `sheet-reader.md`). Writers: `office_edit.php` and one module a kind of change (`office_shift`, `office_sheets`, `office_format`, `office_docx_format`, `office_docx_kind`, `office_docx_paragraphs`, `office_new`); the shared xlsx writer `xlsx_write.php`.

## Which decisions bind it?
- Owner (2026-10-19): no library; the readers are the product's own (SheetJS stale with CVEs, its CDN unreachable): `office-files.md`.
- Owner (2026-11-26, agent channel): the Word editor is typed on a page as in a word processor, not in a box opened at the click; Enter and Backspace act as there, with Undo and Redo (OP1-OP3, PR #11940, #11963: `office-docx-page.md`). The abilities on top: `workflow/decisions-made-product-115.md`.
- Owner (2026-11-19, poll 156 "Tam kapsam"): PHPExcel goes, previews and editing grow, Export to Excel and a shared import reader across the panel (`workflow/decisions-made-product-79.md`). How far it goes, what is never built and the rules every edit keeps: `office-files-scope.md`.

## Which parts are there, by what a person sees?
- Previews, limits, doors and security: `office-files.md`, `office-dom-ceiling.md`, `office-xlsx-cell-values.md`, `office-docx-controls-numbering.md`. View only: `office-csv-preview.md`, `office-ods-preview.md`, `office-xls-preview.md` (Save a copy as .xlsx), `office-pptx-preview.md`.
- Workbook view tools (sort, filter, find, Download as CSV): `office-sheet-tools.md`.
- Excel editing: cells `office-cell-edits.md` and their locks `office-cell-locks.md`; the row and column head menu `office-sheet-head-menu.md` over `office-sheet-rows-columns.md`; the sheet tabs' menu `office-sheet-tabs.md` over `office-sheets.md`; the format bar `office-sheet-format-controls.md` over `office-sheet-format.md`.
- Word editing: the white sheet typed where it stands `office-docx-page.md`; its keys (Enter, Backspace, arrows, paste) `office-docx-page-keys.md`; Undo and Redo `office-docx-undo.md`; paragraph text saved `office-paragraph-edits.md`; Bold, Italic, Underline `office-docx-format.md`; Paragraph style `office-docx-paragraph-kind.md`; added (Enter) and Delete the paragraph `office-docx-add-delete.md`.
- New spreadsheet and New document: `office-new-files.md`. In a channel (who may, replace or copy): `workspace/workspace-office-edits.md`.
- Across the panel: Export to Excel on lists `list-excel-export.md`; importers taking .xlsx, .ods and .xls with the sheet question `sheet-import-upload.md`.

## How is a new part added?
- A workbook save is an ordered list of changes of every kind (`office-save-list.md`); a new kind of workbook change is a step kind of that list. A new writer module is named in its memory file's `module:`.
- Same doors and rights as the edit beside it, every text through `lang()`, controls copied from the bar that is already there (look A). Save stays a button: nothing is saved by itself.
- A new kind of document change keeps the save entries, their order and refusals, adds a new entry in its writer module with its test, and is one change of the document's Undo list (`office-docx-undo.md`).
- Unit tests build their fixture files in the test, then read the written file back with the product's reader (`tests/office_test.php` and the module's own test).
