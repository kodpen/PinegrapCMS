---
name: design-file-shared-form
description: add_design_file.php and edit_design_file.php draw the File Access Control and Description cards through includes/forms/design_file_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/design_file_form.php` (gate `PG_FUNCTIONS_DIR`, loaded with `require_once` after `validate_area_access()`). Done in #2934.
- `pg_design_file_form_access_card($folder_options, $options)`: the File Access Control card. The screen passes the `select_folder()` options (upload: `select_folder($_GET['id'], 0)`; edit: `select_folder($file_folder)`). Options: `design` (the checked Design File switch, edit), `design_checked` (default true; false leaves the switch off), `theme` (null or `array('checked', 'disabled')`: the Theme switch of a CSS file, disabled for a system theme), `extra_rows` (markup after the switches).
- `pg_design_file_form_description_card($description, $compact)`: upload passes `('', true)` for the empty one-row box; edit passes the stored description (escaped, `min-height:85px`).
- `pg_design_file_form_layout($form_cards, $status_cards, $buttons)` (the design file and theme file screens): form column, sticky status column, sticky save bar (`edit-screen-wide-layout.md`). Upload: Files To Upload and Description in the form column, File Access Control in the status column; the limits line stays above the drop area. Edit: Main Informations (name) and Description in the form column; the Summary and File Access Control in the status column.
- `pg_design_file_form_summary_card($top, $rows)`: the Summary card; edit passes the thumbnail (or the missing-file note) and File Size, Dimensions (readable images only) and Access.
- The cards read no database and return the card only. `add_file.php` / `edit_file.php` use them too, with `design_checked` for the Design File switch state (`ui/file-shared-form.md`).
- `add_theme_file.php` and `edit_theme_file.php` use the access card with no options, the layout, and (edit) the Summary card; their Name, Description and switches stay liveform fields in the screens (`theme-screens.md`).

## What stays in the screens?
- Upload: the dropzone script and form, the Files To Upload card, the blocked-name check, the INSERT loop, the notice.
- Edit: the `$stored_name` not-found gate, the blank-name refusal (`file-screen-destinations.md`), the row load, image and editor buttons, the system theme check, rename (only when the name changed and the file is on the disk, `design-file-missing-on-disk.md`), delete, the UPDATE.
- Every query stays in the screens (`db_q_regions_styles_menus_test.php` counts 5 `db_q` in the edit screen); `panel_id_gate_test.php` reads the edit screen's gate strings.

## Which test covers it?
- `tests/design_file_form_test.php`: both cards, the optional Design File and Theme rows, the layout and Summary, and both screens calling them.
