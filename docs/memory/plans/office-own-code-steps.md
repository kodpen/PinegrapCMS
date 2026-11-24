---
name: office-own-code-steps
description: Steps of plan office-own-code: previews OP1-OP4 (.csv, .ods, .xls, .pptx), sheet editing OE1-OE5, document editing OD1-OD3, shared Excel export OS1 and import reader OS2
---

## Where are the order and the rules?
- `plans/office-own-code.md`. A step is marked done here with its PR number.

## Previews
- OP1, .csv and .tsv open as a sheet, delimiter and encoding detected; view only. Done: PR #10484 (`platform/office-csv-preview.md`).
- OP2, .ods opens as a workbook through XL1's reader, with its cell ceiling; view only. Done: PR #10519 (`platform/office-ods-preview.md`).
- OP3, .xls opens as a workbook through XL1's reader; view only, with "Save a copy as .xlsx". Done: PR #10542 (`platform/office-xls-preview.md`).
- OP4, .pptx opens as a list of slides (title, text, pictures, notes); view only. Done: PR #10585 (`platform/office-pptx-preview.md`).

## Sheet editing (.xlsx)
- OE1, rows and columns: insert and delete from a row's or column's head menu. References in the same sheet's formulas, merged ranges and the sheet's dimension are shifted; a workbook where the shift cannot be made safely (a table, a defined name, another sheet's formula or a merged range crossing the line) refuses with its reason.
  - Cut: OE1a (done, PR #10618), the server (`platform/office-sheet-rows-columns.md`); OE1b (done, PR #10637), the head menu.
- OE2, sheets: add, rename, delete and reorder from the sheet tabs; a delete another sheet's formula points to is refused.
  - Cut: OE2a (PR #10692), the server (`platform/office-sheets.md`); OE2b (done, PR #10723), the tabs' menu (`platform/office-sheet-tabs.md`).
- OE3, format of the selected cells: bold, italic, text colour, fill, alignment, number format (general, number, money, percent, date); written as new styles, existing ones untouched.
  - Cut: OE3a (PR #10827), the server's style writer (`platform/office-sheet-format.md`); OE3b (PR #10871), the editor's controls and the preview reading the cells' formats (`platform/office-sheet-format-controls.md`).
  - Done: PR #10827, #10871.
- OE4, tools of the view, nothing written: sort by a column, filter by value, find in the workbook, and "Download as CSV" of the open sheet. Done: PR #11033 (`platform/office-sheet-tools.md`).
- OE5, "New spreadsheet" and "New document" in the File Manager's new menu and a channel's attach menu: an empty .xlsx or .docx written by the product's writer, opened for editing. Done: PR #11119 (`platform/office-new-files.md`).

## Document editing (.docx)
- OD1, bold, italic and underline on selected text. Done: PR #11152 (`platform/office-docx-format.md`).
- OD2, a paragraph's kind: normal, heading 1 to 3, bulleted or numbered list. Done: PR #PRNUM (`platform/office-docx-paragraph-kind.md`).
- OD3, add a paragraph after another and delete one; a paragraph holding a picture, a table cell's last paragraph or a section break is not deleted.

## Software-wide
- OS1 (done, #10483), one "Export to Excel" for panel lists: a helper over the shared xlsx writer (typed columns, a frozen bold head) and one toolbar action. The step's first commit is a census in this file of the panel lists that export CSV today; each gets the Excel export beside its CSV, in the census's order, a and b if it passes the size.

- OS1 census: `plans/office-own-code-os1-census.md`.
- OS2 census: `plans/office-own-code-os2-census.md`.
- OS2 (done, #10523, #10548), one import reader: every importer that takes only CSV today (census first, as OS1) also takes .xlsx, .ods and .xls through XL1's reader, and a workbook with more than one sheet asks which sheet. The product and bank statement imports get the sheet question too.
  - Cut in two (`plans/office-own-code-os2-census.md`): OS2a (done, #10523), the shared helper and sheet question with all nine CSV-only importers (`platform/sheet-import-upload.md`); OS2c (done, #10548), the sheet question for the product and bank statement imports after XL5.
