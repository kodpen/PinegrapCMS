---
name: list-table-quick-edit-design-files
description: All Design Files list quick edit (includes/fn/design_file_list_edit.php): the fields, the design file screen's shared name check and save, the answer that redraws a renamed row
module: design_file_list_edit
---

## Which cells change?
- Every value `edit_design_file.php` changes that the list shows (`pg_design_file_quick_edit_columns()`): Name, Folder (the screen's `select_folder()` choices, coloured by access type) and Description (`textarea`, may be empty, as on the screen). Text: Thumbnail, Type, Size, Optimized (they follow the file), Last Modified, the actions. `tests/design_file_list_edit_test.php` fails for a new column that is neither.
- The design and Theme switches are not list columns: the design switch stays on (off would take the file off this list), the Theme switch is written back as stored.
- A file whose folder the picker does not offer keeps its Folder cell as text.

## Who may change them?
- The screen's rights: role Administrator or Designer (`<= 1`). `view_design_files.php` answers `pg_list_edit_request()` before its access check, so a refusal is JSON; the folders are read only once a user is signed in (`select_folder()` reads the global user).

## How is a change checked and written?
- `includes/forms/design_file_form.php`, shared with the edit screen: `pg_design_file_form_refusal()` (required, a new name the server would run refused like an upload, not ".htaccess", free under `check_name_availability()`) and `pg_design_file_form_save()` (rename on the disk first, a failed rename writes nothing; name, type from the extension, folder, description, design, Theme for a CSS file that is not a system theme; audit and log lines). Plain-text refusals; the screen escapes them into `output_error()`.
- The list builds what the screen would post from the stored file plus the one change (`pg_design_file_quick_post()`). The record must be a design file outside the bin with a safe plain name (`pg_design_file_list_row()`), as the screen's gate asks.

## What does the answer redraw?
- Every cell of the row (`pg_design_file_quick_saved()`): a rename changes the type, the thumbnail, the image buttons and the download link. The row's italics (archived folder) follow on the next load.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
