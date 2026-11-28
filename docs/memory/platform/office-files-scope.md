---
name: office-files-scope
description: How far the own Word and Excel code goes: what is edited (simple formulas too), only shown, never built (charts, pivots, layout, other formulas), the rules every edit keeps
---

## How far does it go?
- Owner (2026-11-19): full scope with the product's own code, PHPExcel removed and no library added; it replaced the "simple editing" scope of 2026-10-19 (`workflow/decisions-made-product-79.md`). The parts and their files: `office-overview.md`.
- An .xlsx: cells (`office-cell-edits.md`) and simple formulas (`office-sheet-formulas.md`), one row or column inserted or deleted (`office-sheet-rows-columns.md`), one sheet added, renamed, deleted or moved (`office-sheets.md`), the selected cells' format (`office-sheet-format.md`), each a save.
- A .docx: paragraph text (`office-paragraph-edits.md`), bold, italic and underline on selected text (`office-docx-format.md`), a paragraph's kind (`office-docx-paragraph-kind.md`), a paragraph added or deleted (`office-docx-add-delete.md`).
- Both: "New spreadsheet" and "New document" (`office-new-files.md`), in the file window and a channel's preview with the same controls.

## Which files are only shown, or not at all?
- Shown, never edited in place: .csv and .tsv (changed in the text editor), .ods, .xls (Save a copy as .xlsx), .pptx as drawn slides with Present and its text list (`office-pptx-slides-preview.md`): `pg_office_kind()`, which every edit asks, gives '' for them. A .pptx is changed only as canvases made from it by Edit in Artifact Canvas, never written back (`office-pptx-present.md`).
- .doc, .ppt (binary): no preview or edit. A channel still takes them; it refuses only the macro forms of the new formats (`workspace/workspace-upload-refusals.md`).
- No library for any of them (SheetJS, docx-preview, mammoth, JSZip, PhpSpreadsheet, PHPWord) and none from a CDN: the readers and writers are the product's own (`office-files.md`). PHPExcel is removed (#10528): importers read through `sheet-reader.md`, the Paraşüt exports are written by the xlsx writer (`erp/parasut-import-sheets.md`).

## What is never built, and why?
- Charts, pivot tables, tracked changes, comments, page layout.
- A formula engine beyond simple formulas. Owner (2026-11-26, `workflow/decisions-made-product-115.md`): simple ones (one sheet's references, the four operations, SUM, AVERAGE, MIN, MAX, COUNT) are typed, saved and worked out again (`office-sheet-formulas.md`). Every other formula is locked, kept with its last stored result and loses it when its sheet is saved (`office-sheet-formula-save.md`).
- An editor that keeps a document's whole layout: it needs a document server such as OnlyOffice, which shared hosting cannot run.

## Which rules does every edit keep?
- It writes only what it changes and carries every other part over as it is; a file it cannot carry safely is refused with its reason, never written damaged. Tests read a written file back with the product's reader and compare untouched parts byte for byte.
- The preview's limits (`office-files.md`, `office-dom-ceiling.md`) and the cell locks (`office-cell-locks.md`) hold for every format; who may edit is who may edit the file; no schema.

## Where may the xlsx reader go next?
- Its twin in `import_products_f.php` walks a sheet the same way and reads cells through the same node helpers (`pg_office_xlsx_cell_parts()`, `pg_office_reader_text()`) but keeps its own value rules and no limits; swapping its walk for `pg_office_xlsx_rows()` would cut an import at the preview's limits. The importers' sheet reader (`sheet-reader.md`) uses the same helpers with no limits.
