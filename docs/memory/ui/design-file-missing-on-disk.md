---
name: design-file-missing-on-disk
description: What the theme, file, design file, comment and quote signature screens do when a files row has no file on the disk; the Turkish "noise" theme file and upgrade step 10.5
---

## What happens when a design file is recorded but not on the disk?
- `edit_theme_css.php`, `edit_javascript.php`, `editor_edit_file.php` open it with an empty text box and the note "The file is recorded here but is not on the disk."; Save writes the file again and unlinks the old one only when it is there (#454).
- `edit_design_file.php` renames the file only when the name changed and the stored file is on the disk (#454).
- `edit_theme_file.php` shows the same note on the screen, renames only when the name changed and the file is there, still saves the record and adds the note as a warning in the themes list after Save (PR #474); before, Save wrote a `rename()` warning.
- `edit_file.php`, `edit_design_file.php` (GET, `$on_disk`, PR #3889): the note in place of the preview, an empty editor, no px line, image tools, Convert cards or Open/Download links (they led to a 404 and wrote PHP warnings). File Size still shows `files.size`.
- `edit_comment.php`: name + "(not on the disk)", no thumbnail or link; Delete Attachment stays. `edit_erp_quote.php`: "The drawing of the signature is missing." (#3889). `pg_signature_display()` still draws a broken image. Saves unchanged; `tests/file_missing_on_disk_screens_test.php`.
- The note is one existing `tr.json` string; no new keys.

## What happens when Save, Duplicate or Create File cannot write the file?
- `edit_theme_css.php`, `edit_javascript.php`, `editor_edit_file.php` (Save and Duplicate) and `create_file.php` write through `pg_file_write_whole()` (`includes/fn/files.php`): a checked temporary file renamed over the stored one; on failure the old file stays and it returns false.
- On false no `files` row is written or updated and no success notice is shown. The editors open again with "{var:1} could not be written to disk." and the typed code (kept in the liveform field `code`, read before `file_get_contents()`); Create File answers with `output_error()` and Go back.
- Before, the editors unlinked and wrote unchecked: lost changes, `fwrite(false)` fatals, cut-off stylesheets.
- Not yet covered: `add_theme_file.php` (rows inserted before the write), `import_design.php` and `update_imported_items.php` (bulk import, would need a summary of failed items), `edit_file.php` / `edit_design_file.php`.

## Why can saving Edit Theme change a theme's name by itself?
- `prepare_file_name()` collapses doubled underscores, so a record named `theme__noise.css` is stored as `theme_noise.css` on the first Save even with the name field untouched. With the file on the disk, the file is renamed with it; that is existing behaviour, left as is.

## What was wrong with the Turkish "noise" theme?
- `data/backups/turkish_default` shipped the file as `theme_ noise.css` (with a space) while its `files` row is `theme__noise.css`, so on every Turkish install the theme had no stylesheet. The starter site carries the right name since #454.
- Upgrade step 10.5 (`upgrade_2026_4_10_noise_theme_file()`, PR #474) renames `data/files/theme_ noise.css` to the name a design row has (`theme__noise.css`, or `theme_noise.css` after an Edit Theme Save), only when the source file exists, such a row exists and nothing is on the disk under that name. Otherwise it does nothing, so a second run, a hand-renamed file or a site without the file are untouched. If `rename()` fails it leaves an upgrade note asking to rename by hand. No table changes, so no `[SCHEME]` changelog line.
- On a site already at 2026.4.10 it runs only through Repair Software › Run the steps again. The row's `size` still differs from the file; display only.

## How was it verified?
- Sandbox: no rename warning on Save, note shown; step 10.5 renamed in both row-name cases and did nothing on reruns or without a matching row. `tests/panel_id_gate_test.php` `test_panel_id_gate_file_editors_check_name_and_type` covers the screens.
