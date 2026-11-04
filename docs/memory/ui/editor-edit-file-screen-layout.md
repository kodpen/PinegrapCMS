---
name: editor-edit-file-screen-layout
description: The design file text editor (editor_edit_file.php: JS, JSON, TXT, SVG, XML) on the wide edit layout: the code card, the Summary rows, the save bar, what stays in the screen
---

## How is the text editor screen laid out?
- Through `pg_design_file_form_layout()` and `pg_design_file_form_summary_card()` (`includes/forms/design_file_form.php`, loaded after the designer gate): the Code card (the `#code` editor, its `<label for="code">` kept for the editor's name) in the form column, the Summary in the status column, Save & Return, Save and Duplicate in the shared sticky save bar. The file name sits in the screen toolbar. The same build as Edit JS and Edit CSS.
- Summary rows: File Name, Type (the extension), Folder, File Size (only when the file is on the disk), Access, Last Modified (relative time and user, when a timestamp is stored).

## What stays in the screen?
- The gate (design file, plain name, text formats only; `panel_id_gate_test.php`), the editor language per extension, the kept typed code, the POST (save, save and return, duplicate). The folder name and last change come from a `db_item()` in the GET branch, so the `db_q` count stays 2 (`db_q_pages_regions_test.php`).

## Which test covers it?
- `tests/editor_edit_file_layout_test.php`.
