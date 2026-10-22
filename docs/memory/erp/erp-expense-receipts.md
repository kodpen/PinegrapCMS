---
name: erp-expense-receipts
description: An ERP expense's receipt file: one door for form and API, type read from the bytes, replacing keeps the old copy (erp_archive_store again), readers take the newest row, MAX(id) in direct joins.
---

## How is a receipt file kept?
- Only through `erp_expense_keep_receipt($id, $bytes, $user_id, $replace)`, the door the form (`erp_expense_attach()`) and the API (`POST /erp/expenses/{id}/receipt`, base64) share. At most `ERP_EXPENSE_FILE_MAX_BYTES` (10 MB).
- The type is read from the bytes (`erp_expense_receipt_type()`: PDF, JPG, PNG, GIF, WEBP), never from the name or what the browser said.
- Kept like every ERP document (`includes/erp/archive.php`, erp-document-archive.md): a `files` row with `erp_doc_type = 'expense'`, served to the ERP right only and read-only in the file manager (docs/memory/platform/erp-documents-in-file-manager.md).

## How is a receipt replaced?
- Only when asked (`$replace`; without it the API answers `409 expense_has_receipt`) and only while `erp_expense_receipt_refusal()` is empty: not for a cancelled expense, not for one dated in a locked period (it went to the accountant with the file it had).
- `erp_archive_store('expense', $id, …, $again = true)` writes a new row beside the old one. The newest row is the expense's file; the old one is never deleted, and `erp_archive_files()` gives the history, newest first (the panel lists the earlier ones as replaced).
- `$again` is only for a picture of paper; a document the ERP printed keeps its first copy, the one that was issued.

## How does code read an expense's file?
- `erp_archive_file($doc_type, $id)` returns the newest row whose file is on disk. A query that joins `files` directly reduces it to one row with `MAX(id)` (as `includes/erp/files.php` and the expense API do); otherwise a replaced receipt lists the expense twice.
