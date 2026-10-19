---
name: files-office-documents
description: Plan (2026-10-19) for docx and xlsx in the file manager and the Workspace: in-browser viewing, cell and paragraph edits that keep the file, send-to-channel, picker; slices D1-D5, open questions
---

## What is this plan?
- Owner request (2026-10-19): open and simply edit `.docx` and `.xlsx` in the file manager, and use the same files in the Workspace ("ortak kullanma").

## What exists today (2026-10-19)?
- File manager (`view_folders.php`, actions in `view_folder_and_files_f.php` via `pg_explorer_handle()`): previews for images (Pintura editor), PDF (browser iframe), video, audio and text (CodeMirror, `pg_explorer_editable_formats()`, 2 MB). Office files show an icon and "No preview"; `get_file.php` has no OOXML MIME types, so they always download.
- No office library anywhere: no SheetJS, mammoth, docx-preview, JSZip, PhpSpreadsheet or PHPWord. Server side has a hand-written xlsx writer (`export_products_f.php`, `includes/erp/accountant.php`; the ERP skill forbids a second one), an XMLReader xlsx reader (`import_products_f.php`), PHPExcel only for xls/ods, pclzip, ZipArchive (optional).
- Workspace files are copies in a private `Workspace/<channel>` folder (`ws_store_upload_bytes()` in includes/workspace/files.php, 20 MB, svg/html/xml/js refused). Edits exist for images and text only (`file_edits.php`: the poster replaces with a version, others reply with a copy; endpoint `workspace_file_save.php`). The only bridge to the file manager is the `#dosya` tag; there is no picker and no "send to channel".

## What are the slices?
- D1 View — done (PR #530, memory docs/memory/platform/office-files.md): read on the server by `includes/fn/office.php` (no library; the SheetJS npm build is stale with known CVEs and its CDN is unreachable), drawn by `assets/js/pg_office_view.js` in the file window, Quick Look and the Workspace preview; OOXML MIME types in `get_file.php`.
- D2 Edit cells: xlsx values edited in the D1 table; saved server side by patching the original package (ZipArchive + DOM on `xl/worksheets/sheetN.xml` and `sharedStrings.xml`), so styles, widths, charts and formulas elsewhere stay untouched; new action `explorer_office_cells_save` (allowlist + `pg_explorer_file_id_actions()` so ERP documents stay read-only); `files.timestamp` as the precondition (last save wins today).
- D3 Edit text: docx paragraph text edited in place (one `<w:p>` at a time, runs merged into the first run's style); same patch approach, same endpoint family; no layout editing.
- D4 Shared use: "Send to channel" in the file manager's context menu (copies through `ws_store_upload_bytes()` with the Workspace refusals, message carries a `#dosya` tag); "From the file manager" in the composer's clip menu listing folders the viewer may read; D1/D2 available on Workspace file cards through `ws_file_edit_target()` (versioned replace).
- D5 Hygiene: block `docm`/`xlsm`/`pptm` on channel uploads, check the zip structure and a size ceiling before parsing, "Where used" counts `ws_messages`.

## What is still open?
1. Settled by D1: no library; the readers are the product's own (the xlsx one can later replace the importer's twin in `import_products_f.php`).
2. Is cell-level / paragraph-level editing what "simple editing" means (default: yes; a full editor that keeps layout needs a document server such as OnlyOffice, which shared hosting cannot run)?
3. `.pptx`: view-only later or out of scope (default: out).
4. Does "shared use" stay a copy into the channel folder (today's model, private channel rules) rather than a reference (default: copy)?

## Where to start?
- D4 next (send-to-channel and the picker), then D2 (xlsx cell edits on the server, reusing `pg_office_xlsx_*`), D3, D5. Memory file for the final behaviour: `docs/memory/platform/office-files.md`. Verify on dev: a 5 MB xlsx with three sheets, a docx with images and tables, dark theme, a private channel member without folder rights.
