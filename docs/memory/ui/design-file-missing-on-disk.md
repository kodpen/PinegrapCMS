---
name: design-file-missing-on-disk
description: What the theme, design file and CSS/JS/text editor screens do when a files row has no file on the disk; the Turkish "noise" theme file and upgrade step 10.5
---

## What happens when a design file is recorded but not on the disk?
- `edit_theme_css.php`, `edit_javascript.php`, `editor_edit_file.php` open it with an empty text box and the note "The file is recorded here but is not on the disk."; Save writes the file again and unlinks the old one only when it is there (#454).
- `edit_design_file.php` renames the file only when the name changed and the stored file is on the disk (#454).
- `edit_theme_file.php` shows the same note on the screen, renames only when the name changed and the file is there, still saves the record (name, folder, description, activation) and adds the same text as a warning in the themes list after Save (PR #PRNUM). Before, every Save wrote a `rename(): No such file or directory` warning.
- The note is one existing `tr.json` string; no new keys.

## Why can saving Edit Theme change a theme's name by itself?
- `prepare_file_name()` collapses doubled underscores, so a record named `theme__noise.css` is stored as `theme_noise.css` on the first Save even with the name field untouched. With the file on the disk, the file is renamed with it; that is existing behaviour, left as is.

## What was wrong with the Turkish "noise" theme?
- `data/backups/turkish_default` shipped the file as `theme_ noise.css` (with a space) while its `files` row is `theme__noise.css`, so on every Turkish install the theme had no stylesheet. The starter site carries the right name since #454.
- Upgrade step 10.5 (`upgrade_2026_4_10_noise_theme_file()`, PR #PRNUM) renames `data/files/theme_ noise.css` to the name a design row has (`theme__noise.css`, or `theme_noise.css` after an Edit Theme Save), only when the source file exists, such a row exists and nothing is on the disk under that name. Otherwise it does nothing, so a second run, a hand-renamed file or a site without the file are untouched. If `rename()` fails it leaves an upgrade note asking to rename by hand. No table changes, so no `[SCHEME]` changelog line.
- On a site already at 2026.4.10 the step runs only through Repair Software › Run the steps again.
- The row's `size` (134322) still differs from the file (127718 bytes); display only.

## How was it verified?
- Sandbox (Turkish starter, MariaDB, PHP built-in server): old code wrote the rename warning on Save; new code wrote none, showed the note and the warning. Step 10.5 renamed in both row-name cases, did nothing on the second run, with both files present, or with no matching row. `tests/panel_id_gate_test.php` `test_panel_id_gate_file_editors_check_name_and_type` covers the screens.
