---
name: list-table-quick-edit-referral-sources
description: All Referral Sources list quick edit (referral_source_list_edit.php): name, code and sort order as fields, the referral source screen's checks and save
module: referral_source_list_edit
---

## Which cells change?
- Every list value `edit_referral_source.php` changes (`pg_referral_source_quick_edit_columns()`): Name and Code (required, 50 characters, the screen's input limit) and Sort Order (a whole number from 0 to 999; blank is 0). Text: Last Modified. `tests/referral_source_list_edit_test.php` fails for a new column that is neither.

## How is a change checked and written?
- The screen's check `pg_referral_source_form_error()` on all three values (the stored two plus the edited one, `pg_referral_source_quick_values()`): required name and code, the sort order bound, and no other source with the same name or code (without case; `pg_store_referral_source_others()`). A stored blank name or code refuses every change until it is set.
- `pg_referral_source_quick_update()` writes through the screen's `pg_store_referral_source_save()` (stamps user and time) and logs "referral source (…) was modified". There is no webhook event for referral sources.
- Rights: the screen's (`validate_ecommerce_access()`); `view_referral_sources.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON. The answer is the row read again (`pg_referral_source_list_sql($id)`, `pg_referral_source_list_cells()`).

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-settings-lists.md
