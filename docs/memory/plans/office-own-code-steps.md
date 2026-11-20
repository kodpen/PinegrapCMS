---
name: office-own-code-steps
description: Steps of plan office-own-code: previews OP1-OP4 (.csv, .ods, .xls, .pptx), sheet editing OE1-OE5, document editing OD1-OD3, shared Excel export OS1 and import reader OS2
---

## Where are the order and the rules?
- `plans/office-own-code.md`. A step is marked done here with its PR number.

## Previews
- OP1, .csv and .tsv open as a sheet: the delimiter (comma, semicolon, tab) and the encoding (UTF-8 with or without a mark, Windows-1254) are detected, a choice above the sheet corrects a wrong guess; view only. Done: PR #10484 (`platform/office-csv-preview.md`).
- OP2, .ods opens as a workbook through XL1's reader, with its cell ceiling; view only. Done: PR #10519 (`platform/office-ods-preview.md`).
- OP3, .xls opens as a workbook through XL1's reader; view only, with "Save a copy as .xlsx" (written by the shared writer beside the file, or as a new message's file in a channel), which the user then edits. Done: PR #10542 (`platform/office-xls-preview.md`).
- OP4, .pptx opens as a list of slides: each slide's title, text in reading order, pictures and speaker notes; no slide layout, no animation; the picture limits of the .docx preview apply. Done: PR #10585 (`platform/office-pptx-preview.md`).

## Sheet editing (.xlsx)
- OE1, rows and columns: insert and delete from a row's or column's head menu. References in the same sheet's formulas, merged ranges and the sheet's dimension are shifted; a workbook where the shift cannot be made safely (a table, a defined name, another sheet's formula or a merged range crossing the line) refuses with its reason.
  - Cut in two: OE1a (done, PR #10618), the server's shift and refusals (`platform/office-sheet-rows-columns.md`). OE1b (done, PR #10637), the grid's head menu that sends it (`pg_office_view.js`).
- OE2, sheets: add, rename, delete and reorder from the sheet tabs; a delete another sheet's formula points to is refused. In progress, stacked on OE1b.
- OE3, format of the selected cells: bold, italic, text colour, fill, alignment, number format (general, number, money, percent, date); written as new styles, existing ones untouched.
- OE4, tools of the view, nothing written: sort by a column, filter by value, find in the workbook, and "Download as CSV" of the open sheet.
- OE5, "New spreadsheet" and "New document" in the File Manager's new menu and a channel's attach menu: an empty .xlsx or .docx written by the product's writer, opened for editing.

## Document editing (.docx)
- OD1, bold, italic and underline on the selected text of a paragraph (runs split and joined; a run's other properties kept).
- OD2, a paragraph's kind: normal, heading 1 to 3, bullet list, numbered list, with the styles and numbering the document already has, or the product's own added when it has none (`platform/office-docx-controls-numbering.md`).
- OD3, add a paragraph after another and delete one; a paragraph holding a picture, a table cell's last paragraph or a section break is not deleted.

## Software-wide
- OS1 (done, #10483), one "Export to Excel" for panel lists: a helper over the shared xlsx writer (typed columns, a frozen bold head) and one toolbar action. The step's first commit is a census in this file of the panel lists that export CSV today; each gets the Excel export beside its CSV, in the census's order, a and b if it passes the size.

- OS1 census: `plans/office-own-code-os1-census.md`.
- OS2 census: `plans/office-own-code-os2-census.md`.
- OS2 (done, #10523, #10548), one import reader: every importer that takes only CSV today (census first, as OS1) also takes .xlsx, .ods and .xls through XL1's reader, and a workbook with more than one sheet asks which sheet. The product and bank statement imports get the sheet question too.
  - Cut in two (`plans/office-own-code-os2-census.md`): OS2a (done, #10523), the shared helper and sheet question with all nine CSV-only importers (`platform/sheet-import-upload.md`); OS2c (done, #10548), the sheet question for the product and bank statement imports after XL5.
