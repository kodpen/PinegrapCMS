---
name: record-edit-in-place-server
description: Server of a collection item's fields corrected in place from the designer's Preview (RE1, #11551): controls, rights, approval, changed-meanwhile check, write order, panel actions
module: record_edit
---

## What does it write?
- One collection item's fields (`custom_form_pages.collection = 1`), never a page's tree: no Publish, no design undo. `includes/fn/record_edit.php`, loaded by `functions.php`.
- Controls (`pg_rec_edit_control()`, pure): `text` (text box, e-mail, plain text area, slug kind), `rich` (formatted text area), `number` (number kind), `date` (date), `picture` (image kind of a file upload). References, products, pages, rows, choices, date and time, time, other files, signatures stay on the item's own screen.

## Who may write, and what about approval?
- `pg_rec_edit_may()`: a Manager or above, or a user with Manage Forms who may edit the form page's folder: whoever Edit Submitted Form lets save as the forms' manager. A form editor or a submitter (they reach that screen only from a form item view page) may not.
- With "Content changes wait for approval" on, a role 2 or 3 change is refused with status `approval` (`pg_rec_edit_waits()`); the read says `waits: true` so the Preview does not offer the switch. Holding a record change in `design_proposals` is not built: its ops and approve path are page-tree only.

## How does the changed-meanwhile check work?
- Each change is `{field_id, value, base}`; `base` is the value the Preview read. `pg_rec_edit_plan()` (pure): stored = value → nothing; stored = base → written; otherwise a conflict `{mine, theirs}`. Any conflict writes nothing and answers `conflict` (409) with both values; keeping either one resends it with `base` = theirs. Compared byte for byte.
- A picture's value is its `files.id` as a string, '' for none.

## In which order does a save write?
- Rights, approval, the sent list (at most 100), conflicts, then the edit screen's checks in a liveform (`pg_submitted_form_check_field()`: dates, e-mail, the Title's address in use, number kind) plus required and, for a picture, a files row named as an image in a folder the person may view.
- Then `pg_rec_edit_write()`: `pg_record_revision_before()`, the field's `form_data` rows replaced (`pg_submitted_form_save_value()`; a picture gets one row with `file_id`), `last_modified_*`, `update_submitted_form_address_name()` when pretty URLs are on, `pg_record_revision_after(..., 'designer')`, the activity log, `pg_tr_records_saved()` for a complete item.
- The picture it pointed at before is not deleted (Edit Submitted Form deletes a replaced upload): a chosen picture may be a library file shown elsewhere.

## Which endpoints?
- `api.php` actions `record_edit_read` and `record_edit_save` (`includes/panel/record_edit.php`): `any_role`, token checked, body `{item_id, changes}`. Statuses: `ok`/`saved`/`unchanged` 200, `missing` 404, `denied` 403, `conflict`/`approval` 409, `invalid`/`refused` 422. Each answer after a read or save carries `record` (id, reference_code, address_name, title, form_name) and `fields` (id, name, label, control, required, value, file_name).

## Which test guards it?
- `tests/record_edit_test.php`; the `designer` source in `tests/record_revisions_test.php`.

Related: docs/memory/designer/collection-revisions.md, docs/memory/designer/content-proposals.md
