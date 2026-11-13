---
name: file-object-storage-readers
description: How readers, in-place doors and passes get a path to a file that may be in the object store; the cached copy's last use and derivative signatures
module: file_storage
---

## Which function gives a reader a path?
- `pg_file_local_copy($file_id)`: a local file under the name wins, even for an 's3' row (a save wrote it); else a copy fetched to `.part` and renamed, reused while its size matches the row. By name: `pg_file_reader_path($name)` (one stat for files that never moved, a row query only when missing). Both give '' when the bytes cannot be had.

## What does a door that changes bytes in place call?
- A door that changes bytes in place (rotate, optimize, office and text saves in the file window) calls `pg_file_make_local($name)`: the copy is copied into the file directory first, the reconcile pass then sets the row back to 'local'. The Design Canvas reads `path` (copy) and writes `local`; a restore writes `local` too.

## What may a pass over many files use?
- A pass over many files must not download: `pg_file_present_path($row)` (local or existing copy). Used by `pg_file_image_dimensions()` and the derivative sweep; a stored file with no copy loses its derivatives there, which is right: a new copy signs anew.

## How is a copy's last use kept?
- Copy last use: `pg_file_cache_used()` moves only the access time; the modification time is the fetch time a derivative's signature reads (`pg_image_derivative_signature()`), so derivatives of a used copy stay valid. The sweep takes `pg_file_cache_last_use()` (later of the two). `pg_file_cache_sweep()` hourly: unused 7 days, `.part` over a day, then LRU above 1 GB. No `GLOB_BRACE` (musl).

## Which readers go through the copy?
- Through the copy (#5904): image editor open/save/restore and SVG open, file window view and save, office preview, Prepare WebP, rotate, optimize, zip, copy, search index text of PDF/office/txt (a file it cannot read keeps its last text). Left local on purpose: front-end `<picture>` helpers (`pg_image_is_animated()` etc. on page render; a stored file gets no srcset), the warm-up, `get_photo_gallery.php` sizes, design and theme files.

Tests: `tests/object_storage_files_test.php`.
