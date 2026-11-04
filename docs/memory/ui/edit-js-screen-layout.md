---
name: edit-js-screen-layout
description: Edit JS (edit_javascript.php) on the wide edit layout: the code card, the Summary rows, the save bar, what stays in the screen
---

## How is the Edit JS screen laid out?
- Through `pg_design_file_form_layout()` and `pg_design_file_form_summary_card()` (`includes/forms/design_file_form.php`, loaded after the designer gate): the Code card (the `#code` editor, its `<label for="code">` kept for the editor's name) in the form column, the Summary in the status column, Save & Return, Save and Duplicate in the shared sticky save bar. The same build as Edit CSS (`edit_theme_css.php`).
- Summary rows: File Name, Folder, File Size (only when the file is on the disk; a missing file shows the note and no size), Access, Last Modified (relative time and user, when a timestamp is stored). No Theme row: a JS file is never a theme.

## What stays in the screen?
- The design-file gate query is unchanged (`panel_id_gate_test.php`); the folder name and the last change come from a second `db_item()` in the GET branch, so the `db_q` count stays 2 (`db_q_pages_regions_test.php`).
- The back URL, the kept typed code, the POST (save, save and return, duplicate).

## Which test covers it?
- `tests/edit_javascript_layout_test.php`.
