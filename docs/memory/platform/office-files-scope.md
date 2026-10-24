---
name: office-files-scope
description: What the Word and Excel support does not do and why: edits stop at cells and paragraphs, no .pptx, no legacy binary forms, no library; where the xlsx reader may go next
---

## What does "simple editing" mean?
- Cell edits in a workbook (`office-cell-edits.md`) and paragraph text in a document (`office-paragraph-edits.md`), in the file window and a channel's preview (owner request 2026-10-19). An editor that keeps the whole layout needs a document server such as OnlyOffice, which shared hosting cannot run, so none is planned.

## Which files are out?
- .pptx: out of scope, no preview; the browser downloads it.
- .doc, .xls, .ppt (binary): no preview or edit. A channel still takes them; it refuses only the macro forms of the new formats (`workspace/workspace-upload-refusals.md`).
- No library is added for any of them (SheetJS, docx-preview, mammoth, JSZip, PhpSpreadsheet, PHPWord): the readers and writers are the product's own (`office-files.md`), as its xlsx writer is (`erp/erp-accountant-pack.md`).

## Where may the xlsx reader go next?
- It can later replace its twin in `import_products_f.php`, which walks a sheet the same way (XMLReader, a row at a time) but still takes each row with `readOuterXml()` (`office-dom-ceiling.md`). Not started.
