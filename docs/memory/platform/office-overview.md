---
name: office-overview
description: Start file for the office-* files: Office files shown and edited by own code in the File Manager and channels; decisions, parts by screen label, which file holds what
module: office
---

## What is it?
- Office files opened in the File Manager (Quick Look, the file window) and a channel's Preview, read and written by own code, drawn by `assets/js/pg_office_view.js` with the same controls in both.
- Readers: `includes/fn/office.php` (`pg_office_read()`, .ods and .xls `sheet-reader.md`). Writers: `office_edit.php` and one module a kind of change (`office_shift`, `office_sheets`, `office_format`, `office_size`, `office_docx_format`, `office_docx_kind`, `office_docx_paragraphs`, `office_docx_link`, `office_docx_table`, `office_docx_picture`, `office_new`); the shared xlsx writer `xlsx_write.php`; simple formulas `office_formula.php` with its twin `assets/js/pg_office_formula.js`.

## Which decisions bind it?
- Owner (2026-10-19): no library; the readers are the product's own: `office-files.md`.
- Owner (2026-11-26, agent channel): the Word editor is typed on a page as in a word processor, with Undo and Redo (`office-docx-page.md`).
- Owner (2026-11-26, poll 235): all eight abilities for both editors, built (`workflow/decisions-made-product-115.md`); simple formulas by own code, every other formula locked (`office-sheet-formulas.md`). Nothing more is added in place (`office-direction.md`).
- Owner (2026-11-26, in the architect's window): the workbook editor is steady: every change waits in one list, drawn at once, saved together, with Undo and Redo; no "save first" refusal.
- Owner (2026-11-19, poll 156 "Tam kapsam"): PHPExcel goes, previews and editing grow, Export to Excel and a shared import reader across the panel (`workflow/decisions-made-product-79.md`). How far it goes, what is never built and the rules every edit keeps: `office-files-scope.md`.

## Which parts are there, by what a person sees?
- Previews, limits, doors and security: `office-files.md`, `office-dom-ceiling.md`, `office-xlsx-cell-values.md`, `office-docx-controls-numbering.md`. View only: `office-csv-preview.md`, `office-ods-preview.md`, `office-xls-preview.md` (Save a copy as .xlsx), `office-pptx-preview.md`.
- Workbook view tools (sort, filter, find, Download as CSV): `office-sheet-tools.md`.
- Excel editing: the list of changes `office-sheet-change-list.md`, its save `office-save-list.md`, Undo and Redo `office-sheet-undo.md`; cells `office-cell-edits.md` and their locks `office-cell-locks.md`; simple formulas `office-sheet-formulas.md`; the row and column head menu `office-sheet-head-menu.md` over `office-sheet-rows-columns.md`; the sheet tabs' menu `office-sheet-tabs.md` over `office-sheets.md`.
- Workbook formats: the bar `office-sheet-format-controls.md`, `office-sheet-bar-text-borders.md` over `office-sheet-format.md`, `office-sheet-format-borders.md`; sizes, Freeze top row `office-sheet-sizes-grid.md` over `office-sheet-sizes.md`; copy, paste, fill down, clear `office-sheet-clipboard.md`.
- Word editing (the page, its keys, Undo, text, formats, styles, links, tables, pictures, paragraphs): `office-docx-overview.md`.
- New spreadsheet and New document: `office-new-files.md`. In a channel (who may, replace or copy): `workspace/workspace-office-edits.md`.
- Across the panel: Export to Excel on lists `list-excel-export.md`; importers with the sheet question `sheet-import-upload.md`.

## How is a new part added?
- A new kind of workbook change is a step kind of the save list with its writer's refusals, an entry kind of the view's list, drawn by `listBook()`, one change of Undo. A new writer module is named in its memory file's `module:`.
- Same doors and rights as the edit beside it, every text through `lang()`, controls copied from the bar (look A). Save stays a button.
- A new kind of document change keeps the save entries, their order and refusals, adds a new entry in its writer module with its test, and is one change of the document's Undo list (`office-docx-undo.md`).
