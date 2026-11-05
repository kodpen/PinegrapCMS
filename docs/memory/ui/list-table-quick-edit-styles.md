---
name: list-table-quick-edit-styles
description: All Page Styles list quick edit (includes/fn/style_list_edit.php): the fields, which rows stay text, the custom style screen's shared check and save
module: style_list_edit
---

## Which cells change?
- Every one-line value `edit_custom_style.php` changes that the list shows (`pg_style_quick_edit_columns()`): Name, Collection (A/B), Layout Type (no override, System, Custom). Text: Folders/Pages Using Style (computed), Theme (the custom style screen does not change it), Last Modified, the designer button. `tests/style_list_edit_test.php` fails for a new column that is neither.
- The code (several lines) and the social networking position are not list columns; they stay on the screen.
- Only a custom style's cells are fields (`pg_style_quick_cell_editable()`); a system style opens in the visual designer, its row stays text and the write refuses it.

## Who may change them?
- The style screen's rights: role Administrator or Designer (`<= 1`). `view_styles.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

## How is a change checked and written?
- `includes/forms/custom_style_form.php`, shared with the edit screen: `pg_custom_style_form_values()` (trims the name, a pick list value outside its list falls back to its default), `pg_custom_style_form_name_refusal()` (required, at most 100 characters, held by no other style, the unchanged name not checked) and `pg_custom_style_form_update()` (UPDATE, stamp, log line).
- The list builds what the screen would post from the stored style plus the one change (`pg_style_quick_post()`), so the code and the position are written back as stored.
- The answer redraws the name, the pick lists and Last Modified (`pg_style_quick_saved()`); the counts do not change.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
