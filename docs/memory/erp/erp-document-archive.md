---
name: erp-document-archive
description: ERP documents kept as issued (4.65, includes/erp/archive.php): what is kept, where and under which name, the deferred render, get_file.php's ERP gate, the file manager's ERP folders.
---

## What is kept, and where?
- The PDF an invoice or delivery note was issued with, the provider's copy of an e-document once GİB accepted it (only when the bytes are a PDF), every e-mailed reconciliation letter (with an `erp_reconciliation_log` row) and expense receipts (a new photo is kept beside the old one, `$again`: erp-expense-receipts.md).
- Through `includes/erp/archive.php` only (`erp_archive_store()`): the file directory (`FILE_DIRECTORY_PATH`) and a `files` row with `folder = 0`, `erp_doc_type` / `erp_doc_id` set, named `erp-<type>-<number>-<random>.pdf`. Never a new folder or a write path of its own. The first copy is the document; a later call returns it.
- Written as a temporary file then renamed. Do not ask `is_writable()` about the folder (on Windows it reads the read-only attribute); the write's result is the answer.

## When is it rendered?
- Asked for where the document is announced (`erp_archive_defer()` from `erp_event_invoice()` / `erp_event_waybill()`), which is inside the writer's transaction; the render runs at the end of the request, so a rolled-back writer leaves nothing to keep. The queue empties as it runs, so a document written during shutdown (the automatic invoice) still gets its copy.
- Endpoints serve `erp_archive_file()` first; a live render only for the template preview, `?html=1`, or a document issued before 4.65 (its first opening keeps it). Move to today replaces the kept PDF.

## Who may open one?
- `get_file.php`: a file named `erp-…` whose `erp_doc_type` is set is served only to a signed-in user with the ERP right (role below 3 or `manage_erp`); anyone else gets a 404. Without that check a folderless file is public, and an invoice is personal data.
- A new query that lists files leaves ERP rows out (`erp_doc_type = ''`); a file operation by id (delete, move, rename, replace) refuses them: docs/memory/platform/erp-documents-in-file-manager.md.

## How does the file manager show them?
- `includes/erp/files.php` owns the virtual folders (`erp_files_folders()`), their scope, counts, lists and item payloads; `view_folders.php` only draws them (label, icon, "Go to …" and preview by `erp_kind`). A new folder or kind updates both.
- A picture item (expense receipt) carries `picture: true` and keeps `is_image` false: `is_image` would offer the image editor, optimising and the picture filters on a document.
- The e-document folders appear only when e-documents are in use or already hold something.
