---
name: record-gate-request-id
description: A record screen's access gate checks the id its handlers read ($_POST on a submit, $_GET on the form), never $_REQUEST, which a cookie of the same name can feed
---

## Which id does a screen's role or folder gate check?
- The one its handlers act on: `$requested_<record>_id = $_POST ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');`, then `$id = is_scalar(...) ? (int) ... : 0;` and the "Record not found." gate on `$id`.
- Screens that do it: `edit_page.php` (folder access), `edit_user.php` (role ceiling).

## Why not `$_REQUEST['id']`?
- Where no `request_order` is set (a server without php.ini, e.g. the official Docker images), `$_REQUEST` follows `variables_order` `EGPCS`: a cookie outranks POST. A cookie `id` naming a permitted record passed the gate while the POST `id` was the record changed.
- `edit_user.php` before the bug-hunt fix: a Manager with a cookie `id` naming a role-3 user could delete an administrator, or change their e-mail, password and rights, through the POST `id`.

## Which test holds it?
- `tests/panel_id_gate_test.php`: `test_panel_id_gate_edit_user_checks_handler_id` (no `$_REQUEST['id']` left in `edit_user.php`); the `$_GET['id']` cast scan accepts the `$requested_<record>_id` gate.
