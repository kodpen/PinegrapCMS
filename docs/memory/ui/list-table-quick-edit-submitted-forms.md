---
name: list-table-quick-edit-submitted-forms
description: Submitted forms list quick edit (includes/fn/submitted_form_list_edit.php): Status and Form Editor, the required-field check before completing, the screen's rights and side effects
module: submitted_form_list_edit
---

## Which cells change?
- The record's own values `edit_submitted_form.php` changes (`pg_submitted_form_quick_edit_columns()`): Status (Complete / Incomplete, the screen's Convert buttons) and Form Editor (a username, empty for none; a new column, sortable, after User). Text: Custom Form, Reference Code, Payment, Contact, User, Submitted, Last Modified. The field values differ per custom form and stay on the screen. `tests/submitted_form_list_edit_test.php` fails for a new column that is neither.
- `pg_submitted_form_list_columns($payment, $edit)`: the two are `editable` only with `$edit`; the source always passes true, since every row on the list is one the viewer manages.
- The cells get their attributes in `pg_submitted_form_list_rows()` through `pg_submitted_form_quick_cells()`, so `pg_submitted_form_list_row()` keeps its signature.

## Who may change them?
- `view_submitted_forms.php` answers `pg_list_edit_request()` before its own gate: role below User or `manage_forms`; then `pg_submitted_form_quick_update()` checks the form's folder (`check_edit_access()`) for a role-3 user, as the screen's submitted forms manager rule (403 `denied`).

## How is a change checked and written?
- Complete: every required field (not information) needs a stored value, a file for an upload field (`pg_submitted_form_quick_missing()`, pure); the screen's own required check, without its per-type format checks (the stored values were checked when written). Incomplete needs nothing.
- Form Editor: a username that exists (the screen's message otherwise), stored as the user's own spelling; empty sets 0.
- The write sets the column, `last_modified_user_id` and time, records the revision (`pg_record_revision_before/after()`, the snapshot holds `complete`), sends the custom form's e-mails and auto campaigns when a form was just completed (`pg_send_custom_form_notifications(…, true)`), logs "submitted form (…) was modified". The same value writes nothing.
- The answer is the row read again with the list's source (`pg_list_table_record_row()`): the cell and Last Modified.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
