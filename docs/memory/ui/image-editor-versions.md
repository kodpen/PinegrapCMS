---
name: image-editor-versions
description: The image editor's versions (file_versions, 2026.11.0): base and save rows, what opening loads, what a save, restore and delete write, retention, the drawer, deleting a file, Disk Usage
module: image_editor
---

## What does a version row hold?
- `file_versions` (step `upgrade_2026_11_0_file_versions()`, 11.0.140; columns in `workflow/decisions-made-schema-7.md`, #4202). Pixels in `data/file_versions/<file>-<no>-<digest>.<ext>` (`pg_file_versions_stored_name()`); `data/` answers no URL, the browser reads them through `image_editor_api.php` action `picture` (bytes, not JSON; a blob URL on the stage).
- A base row has `doc_json = ''`: pixels nobody's document is drawn into. Version 1, the original, is one; later ones are the file as it was changed outside the editor (File Manager rotate, Optimize, an upload over it), or a save whose document could not be kept.
- A save row keeps the result pixels and the document; `source.base` in it is the version_no of the base row it was drawn on (set by the server, `pg_file_versions_doc_json()`). `pg_image_editor_api()` decodes the body once as objects (`pg_image_editor_arrays()` gives the checks arrays), so the version keeps `{}` as an object (an array decode wrote `[]` and a tool's later field was lost).

## What does opening load?
- `pg_file_versions_open_state()`: when the file is byte for byte the newest version (`pg_file_versions_matches_file()`, size then SHA-1) and that one has a document whose base row still exists, `open` answers `doc` and `base` (version id); the browser loads that base's pixels with the document (`M.versionOpenDoc()`), so crop, turns, adjustments and layers edit again. Otherwise it opens the file as it is.

## What does a save write?
- Replace: `pg_file_versions_before_save()` before the pixels are made: with `base` (the version the browser loaded) the pixels come from that row; a file changed outside since the newest version is kept first as a base row. Without `base` the file itself is the base, kept as a row unless the newest version is a base row holding it (version 1 at the first save). Then the file is written, then `pg_file_versions_after_save()` adds the save row (none when `doc_hash` equals the newest's) and prunes.
- Copy renders from `base` too and writes no versions; new (an artboard) writes none (E12 decides the canvas's).
- A document over 1 MB or holding a data: picture (a pasted image) is not kept: the row becomes a base row, the layers flattened into its pixels.

## What do restore and delete do?
- `restore {file_id, version_id}`: the version's bytes become the file (same id and name, `pg_image_editor_record()`), a `restore` row copies its pixels and document, the editor reopens from the answer with a fresh history; closing afterwards still hands the caller the file (`onSave`). A dirty editor asks once on the button ("Discard changes and restore").
- `delete_version`: never version 1 or the newest. Both need replace rights on the file.

## How many are kept and where are they counted?
- The original plus the last 10 saves (#4203), and the base rows those saves are drawn on (`pg_file_versions_pruned()`, pure): pruning a base made the newest document open with its layers burned in.
- Deleting a file for good deletes its rows and pictures: `pg_file_versions_delete_file()` in the File Manager, the Recycle Bin, edit_file.php, edit_files.php, edit_design_file.php; the general job's daily `pg_file_versions_sweep()` takes the versions of files deleted elsewhere (designer, theme files, attachments). `pg_files_disk_usage()` adds their bytes to Disk Usage.
- A redacted save writes `redacted = 1`, `source = 'redact'` (`ui/image-editor-redact.md`).

## What does the drawer show?
- The top bar's Versions button with the count (icon only on a phone) opens the panel as the drawer (screen 10): each version with when, who ("You" for the one asking), kind, size, Preview (the picture over the stage, Back to editing), Restore, Delete. Picking a tool closes it.
