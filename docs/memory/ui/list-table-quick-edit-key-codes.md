---
name: list-table-quick-edit-key-codes
description: All Key Codes list quick edit (includes/fn/key_code_list_edit.php): the fields, the key code screen's own check and save, notes on several lines, who may change them
module: key_code_list_edit
---

## Which cells change?
- Every list value `edit_key_code.php` changes (`pg_key_code_edit_columns()`): Key Code, Offer Code, Enabled, Expiration Date (typed in the site's date order, blank for never), Notes (`textarea`, lines kept), Single-Use, Report. Text: the offer's message (the offer's own field) and Last Modified. `tests/key_code_list_edit_test.php` fails for a new column that is neither.
- Before the `textarea` type, notes written on more lines stayed text (a one-line box would have joined them).
- Before, only Enabled was a field.

## Who may change them?
- The key code screen's rights (`validate_ecommerce_access()`): role below User, or a User with `manage_ecommerce`. `view_key_codes.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

## How is a change checked and written?
- `pg_key_code_quick_update()` fills a throwaway liveform (`key_code_quick_edit`) with the stored key code as the screen opens it plus the one change (`pg_key_code_quick_form_values()`; `enabled_was` is the stored flag, an off switch posts empty), runs the screen's required check and `pg_key_code_form_validate()` (code free among key codes and offers' own codes, offer found, 50 characters, real date) and writes the screen's `pg_key_code_form_values()` with its UPDATE. The first field error is the refusal's message. Unchanged values write nothing; a change logs "Key code (…) was changed on the key code list".
- The answer is the row read again (`pg_key_code_list_sql($id)`, `pg_key_code_list_row_cells()`): every cell, so the code's colour follows Enabled, the date and the offer code. `pg_key_code_active()` and `pg_key_code_list_cells()` stay in `includes/fn/ecommerce.php`.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
