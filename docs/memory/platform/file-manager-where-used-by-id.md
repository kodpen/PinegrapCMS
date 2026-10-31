---
name: file-manager-where-used-by-id
description: "Where used" counts form, comment and contact attachments by file id, only those the viewer may open; Edit Submitted Form marks a binned or deleted attachment
---

## Which records are read by file id?
- Submitted form attachments (`form_data.file_id` → `forms`, named by `custom_form_pages.form_name`, note the reference code, `edit_submitted_form.php`), comment attachments (`comments.file_id`, named by the author, note the page label, `edit_comment.php`) and contact pictures (`contacts.file_id`, probed first, `edit_contact.php`). Before, a form attachment answered "Nothing on the site points to this file." and deleting it emptied the record's attachment (#4035, PR #4198).
- Unlike the name sources, a record the viewer may not open is left out, row and count both (`pg_explorer_file_usage_bound_group()`): forms `pg_explorer_file_usage_form_open()` (role <= 2, or `manage_forms` and `check_edit_access()` of the form page's folder), comments `check_edit_access()` of the page's folder, contacts `validate_contacts_access($user, true)` and `validate_contact_access()`. Up to 200 newest rows are read, then filtered, then capped at 40.
- Edit Submitted Form reads the attachment's state with `pg_submitted_form_attachment_state()` (`includes/forms/submitted_form_form.php`): in the bin (parked `pgbin_<id>_` name, shown without it, or a folder in the bin) it warns under the link; with the files row gone it says the file was deleted instead of an empty box.

## Where are the tests?
- `tests/file_usage_bound_records_test.php`: the groups, the access limit, the items, the source pins and the attachment state.
