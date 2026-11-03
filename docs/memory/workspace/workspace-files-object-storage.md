---
name: workspace-files-object-storage
description: Workspace channel files, note attachments, guest files and ERP documents in the object store: which functions read them, which places still write the file directory, the guard test
module: file_storage
---

## Do Workspace and ERP files move to the object store?
- Yes, with no rule of their own: a channel file, a note attachment, a voice message and an ERP document (invoice, waybill, e-document copy, expense receipt, signature drawing) are `files` rows in the file directory, so the move job (`platform/file-object-storage.md`) sends them like any upload. Their addresses stay `get_file.php` names, which redirect after the access checks.

## How does a Workspace or ERP reader get the bytes?
- By name `pg_file_reader_path()`, by row `pg_file_local_copy()` (`platform/file-object-storage-readers.md`): office preview and its edit, the text window, a bulk create's table, the library's send, the voice transcript, the canvas editor's render (`workspace_file_save.php`), `GET /workspace/messages/{id}/file` (and its picture sizes through `ws_file_preview()`, which keeps the derivative cache), the guest page's file (streamed, the store's address never reaches a guest), the notes export, `erp_archive_file()` (every PDF door, the accountant pack, the API), the logo of an ERP document, the signature drawing and its hash check.
- A screen that only says whether a file is there asks `pg_file_kept($name)` (local file or stored row, no fetch): the quote's signature card. The receipt history (`erp_archive_files()`) counts a stored row as kept and links its address.

## Which places still name the file directory?
- Writers only: a new channel file (`ws_store_file_bytes()`) and its discard, a note's new attachment, a text file's save in place (`ws_change_write` for `content`: the reconcile pass sets the row back to local), the archive's new document, and the history list's link path. `tests/object_storage_workspace_test.php` fails on any other line building `FILE_DIRECTORY_PATH . '/'` in `includes/workspace/`, `includes/erp/`, `workspace*.php` or `*erp*.php`.

Tests: `tests/object_storage_workspace_test.php`.
