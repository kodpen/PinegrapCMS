---
name: edit-submitted-form-id
description: edit_submitted_form.php reads, saves and deletes only the id it checked ($id from $_REQUEST, whole number), never $_POST['id'] or $_GET['id']
---

## Which id does the Edit Submitted Form screen use?
- `edit_submitted_form.php` takes `$id` once at the top: a scalar `$_REQUEST['id']` as a whole number, refused with "Record not found." (404) when no submitted form has it.
- The access check, the screen's queries, the save (form row, field values, files, revisions, address name, notifications) and the delete all use that `$id`.

## What went wrong before?
- The access check read `$_REQUEST['id']`, the save and delete `$_POST['id']`. Where `$_REQUEST` also carries cookies (no `request_order` in php.ini, so `variables_order` EGPCS puts cookies last), a cookie `id` naming a record the user may edit passed the check while the posted id named another record, which was then saved or deleted. Fixed in #6098; test `tests/submitted_form_edit_id_test.php`.
