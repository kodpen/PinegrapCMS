---
name: files-office-documents
description: Plan (2026-10-19) for docx and xlsx in the file manager and the Workspace: viewing, channel use, xlsx cell and docx paragraph edits done; hygiene (D5) left
---

## What is this plan?
- Owner request (2026-10-19): open and simply edit `.docx` and `.xlsx` in the file manager, and use the same files in the Workspace ("ortak kullanma").

## What exists today (2026-10-19)?
- File manager (`view_folders.php`, actions in `view_folder_and_files_f.php` via `pg_explorer_handle()`): previews for images (Pintura editor), PDF (browser iframe), video, audio and text (CodeMirror, `pg_explorer_editable_formats()`, 2 MB). Office files show an icon and "No preview"; `get_file.php` has no OOXML MIME types, so they always download.
- No office library anywhere: no SheetJS, mammoth, docx-preview, JSZip, PhpSpreadsheet or PHPWord. Server side has a hand-written xlsx writer (`export_products_f.php`, `includes/erp/accountant.php`; the ERP skill forbids a second one), an XMLReader xlsx reader (`import_products_f.php`), PHPExcel only for xls/ods, pclzip, ZipArchive (optional).
- Workspace files are copies in a private `Workspace/<channel>` folder (`ws_store_upload_bytes()` in includes/workspace/files.php, 20 MB, svg/html/xml/js refused). Edits exist for images and text only (`file_edits.php`: the poster replaces with a version, others reply with a copy; endpoint `workspace_file_save.php`). Bridges to the file manager: the `#dosya` tag, and since D4 the send-to-channel entry and the picker.

## What are the slices?
- D1 View — done (PR #530, memory docs/memory/platform/office-files.md): read on the server by `includes/fn/office.php` (no library; the SheetJS npm build is stale with known CVEs and its CDN is unreachable), drawn by `assets/js/pg_office_view.js` in the file window, Quick Look and the Workspace preview; OOXML MIME types in `get_file.php`.
- D2 Edit cells — done: file window (PR #656, memory docs/memory/platform/office-cell-edits.md), channel preview window (PR #656, docs/memory/workspace/workspace-office-edits.md). File window: the D1 table becomes an editable grid; the window's own Save (`explorer_file_save` with `cells`, already ERP-gated) patches only the changed rows, text as inline strings (sharedStrings untouched), and takes every formula's cached result out. Changed from the sketch: no separate action; a hash of the bytes, not `files.timestamp`, as the version.
- D3 Edit text — done (memory docs/memory/platform/office-paragraph-edits.md): a paragraph's plain text, in the file window and the channel preview; only word/document.xml is rewritten. Changed from the sketch: no merging into the first run's style; a word-level diff touches only the runs it reaches.
- D4 Shared use — done (PR #564, memory docs/memory/workspace/workspace-file-manager-files.md): "Send to a channel" in the file manager, "From the file manager" under the paperclip; a copy, no tag of the original.
- D5 Hygiene: block `docm`/`xlsm`/`pptm` on channel uploads, "Where used" counts `ws_messages`, size ceilings that also hold for parts read as a stream (`pg_office_open()` measures only what entries declare). Done: the split comment above `explorer_file_usage` (PR #656).

## What is still open?
1. Settled by D1: no library; the readers are the product's own (the xlsx one can later replace the importer's twin in `import_products_f.php`).
2. Is cell-level / paragraph-level editing what "simple editing" means (default: yes; a full editor that keeps layout needs a document server such as OnlyOffice, which shared hosting cannot run)?
3. `.pptx`: view-only later or out of scope (default: out).
4. Settled by D4: shared use is a copy into the channel folder (private channel rules stay the channel's).

## Where to start?
- D5 next (channel upload rule for macro files, "Where used" with `ws_messages`). Memory file for the final behaviour: `docs/memory/platform/office-files.md`. Verify on dev: a 5 MB xlsx with three sheets, a docx with images and tables, dark theme, a private channel member without folder rights.
