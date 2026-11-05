---
name: object-storage-unmovable-come-home
description: A stored file that a rename or the design flag makes unmovable (design file, .css, .js) is brought back to the disk by the door that changed it: pg_file_storage_bring_home()
module: files
---

## Which files must never stay in the object store?
- Those `pg_file_storage_movable()` refuses: design and theme files, `.css`/`.js`, `dkim.key`. The design screens, the code editors (`editor_edit_file.php`, `edit_design_file.php`), the design export (`pg_migration_design_files()`) read them only by path, and `get_file.php` rewrites `{path}` in a style sheet or script only from the disk.

## What went wrong before?
- The move job only checks this when it takes a row. A file already stored that was then marked as a design file (file window, bulk edit) or renamed to `.css`/`.js` (file window, explorer rename) stayed in the store: the design file editor opened it empty with "not on the disk" and Save wrote the typed text over it; the design export left it out.

## What happens now?
- `pg_file_storage_comes_home($row)` (pure, `includes/fn/files.php`): stored row (`pg_file_storage()` 's3') that is not movable.
- `pg_file_storage_bring_home($ids)`: after the door's `UPDATE`, every such row gets its bytes copied into the file directory with `pg_file_make_local()`; the general job's reconcile pass then sets the row to local and the move job never takes it again. A fetch that fails leaves the row stored (still served by redirect) and writes a Site Log line.
- Called by `handle_file_window.php` (save), `handle_items.php` (file rename), `handle_bulk_edit.php` (design flag set to 1).

Tests: `tests/object_storage_unmovable_home_test.php`.
