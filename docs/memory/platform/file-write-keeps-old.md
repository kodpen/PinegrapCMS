---
name: file-write-keeps-old
description: Writing over a stored file goes through pg_file_write_whole(): the screens and passes that do, what a failed write leaves, and the writers not yet moved
---

## How is a stored file written over?
- Through `pg_file_write_whole($path, $content)` (`includes/fn/files.php`): a checked temporary file in the same folder renamed over the stored one (on Windows the old file is deleted and the rename tried again). On false the old file stays and no temporary file is left.
- A replacement that changes the name or format (SVG > PNG) writes the new file first and deletes the old one only after a whole write. Deleting first loses both on a full disk or an unwritable folder.

## Which writers use it, and what do they do on false?
- `edit_theme_css.php`, `edit_javascript.php`, `editor_edit_file.php` (Save and Duplicate), `create_file.php`, `add_theme_file.php`, `theme_designer.php`.
- On false no `files` row is written or updated and no success notice is shown. The editors open again with "{var:1} could not be written to disk." and the typed code (kept in the liveform field `code`, read before `file_get_contents()`); Create File answers with `output_error()` and Go back.
- Before: unchecked writes, lost changes, `fwrite(false)` fatals, cut-off stylesheets.
- Also through the helper: Edit File's `code` save (a file renamed by the same save goes back; refused before the record changes), its SVG > PNG/WebP (SVG removed only after a whole write; before, a failure lost both), `update_imported_items.php` (size updated only after the write, else a log line), `pg_designer_sync_asset_files()` (no record on failure), and each file Import My Site (`import_design.php`) and Import ZIP (`import_zip.php`) create (#8535: on false a backed-up old file is moved back under its own name, then its row renamed back; no row is inserted; the item is listed as not imported, "could not be written to disk"); `tests/file_replace_keeps_old_test.php`.
- Not yet covered: the designer's file `save` sub-action (`includes/panel/designer.php`). `edit_design_file.php` writes no content; its only disk change is the checked rename in `includes/forms/design_file_form.php`.
