---
name: audit-trail-file-content
description: How a save that writes a file's content gets an audit line even when the size stays the same (content_sha1 on both sides)
module: audit
---

## Why a digest?
- The content lives on disk, not in `files`; `timestamp` and `user` are left out of the comparison, so a same-size edit left both sides equal and wrote no line while the Site Log said "modified" (#4156). A size change showed only `size`.

## How is it written?
- `pg_audit_fetch_file($id, $path)` before the file is written, `pg_audit_record_file($id, $before, $path)` after the UPDATE. Both sides get `content_sha1` (`pg_audit_with_content()`, `pg_audit_file_sha1()`); an unreadable file adds no field, no row stays no row.
- `content_sha1` is not masked; a name ending in `_hash` would be (`pg_audit_secret_key()`).

## Which saves use it?
- `editor_edit_file.php`, `edit_javascript.php`, `edit_theme_css.php` (the read moved above `pg_file_write_whole()`), Edit File (`edit_file.php`) when it writes posted `code` to an editable format (digest read from the stored name before the rename, recorded with the new name; #4285), and the file manager's window save (`includes/explorer/handle_file_window.php`) when it posts content, cells or paragraphs; after a rename the new path is hashed. A name, folder or description save alone does not read the file.
- Uploads, copies and creates keep their own `create` lines without a digest.

## Tests?
- `tests/audit_file_content_test.php`, `tests/audit_design_file_writers_test.php` (read before the write, record after the UPDATE). PR #4260; Edit File in PR #4316.

Related: docs/memory/core/audit-trail-writers.md, docs/memory/core/audit-trail-stored-values.md
