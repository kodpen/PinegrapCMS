---
name: auto-dialog-shared-form
description: add_auto_dialog.php and edit_auto_dialog.php share the template and includes/forms/auto_dialog_form.php (checks, page clean-up, saved values); what stays in the screens
---

## What is shared?
- The markup: `includes/templates/edit_auto_dialog.php`, switched by `$screen` (`create` / `edit`).
- The save: `includes/forms/auto_dialog_form.php` (gate `PG_FUNCTIONS_DIR`), loaded with `require_once` after `validate_area_access($user, 'manager')`.
- `pg_auto_dialog_form_validate($liveform, $id)` (`null` on create, the gated `$id` on edit): name and URL required, then, only when a name was given, the name not used by another auto dialog (`db_q()`).
- `pg_auto_dialog_form_page($page)`: drops one leading slash (a URL typed instead of a page name).
- `pg_auto_dialog_form_values($liveform)`: name, enabled, url, width, height, delay, frequency, page (cleaned), in INSERT/UPDATE order; the screens `array_merge()` the user id (and the edit id) after it. INSERT and UPDATE use `db_q()`.

## What stays in the screens?
- Create: the INSERT, the log, the preview link in the notice, the redirect.
- Edit: the id gate and not-found guard (`panel_id_gate_test.php` reads it), the record load and prefill, the preview URL, delete, the UPDATE, the log, the redirects. The id comes from `$_REQUEST` (the template has no hidden id field); the POST branch uses the gated `$id`.

## Which test covers it?
- `tests/auto_dialog_form_test.php`: the page clean-up, the value order with a stub liveform, both screens on the helpers with no copy of the checks.
