---
name: auto-dialog-shared-form
description: add_auto_dialog.php and edit_auto_dialog.php share the template and includes/forms/auto_dialog_form.php (checks, page clean-up, saved values); what stays in the screens
---

## What is shared?
- The markup: `includes/templates/edit_auto_dialog.php`, switched by `$screen` (`create` / `edit`), in the wide edit-screen layout (`edit-screen-wide-layout.md`, B11): a Dialog Options card (name, URL, page) and a Display card (width, height, delay, frequency) in the form column; the sticky status column holds, on edit, the Enabled switch, then a Summary of page, size, delay and frequency (following the boxes as typed; blank reads All pages / Auto / Immediately / Once) and, on edit, Last Modified (a dash with no stored time); the sticky save bar.
- The save: `includes/forms/auto_dialog_form.php` (gate `PG_FUNCTIONS_DIR`), loaded with `require_once` after `validate_area_access($user, 'manager')`.
- `pg_auto_dialog_form_validate($liveform, $id)` (`null` on create, the gated `$id` on edit): name and URL required; name, URL and page no longer than their columns (100, 255, 100); width, height, delay and frequency blank or a whole number up to `pg_auto_dialog_form_number_limits()`; then, only when a name was given, the name not used by another auto dialog (`db_q()`).
- `pg_auto_dialog_form_whole_number($raw, $max)`: `''` blank, the digits, or `null` (refused). Groups of three with `.` or `,` are thousands ("2.400" is 2400); a sign or a decimal part is refused.
- `pg_auto_dialog_form_page($page)`: drops one leading slash (a URL typed instead of a page name).
- `pg_auto_dialog_form_values($liveform)`: name, enabled, url, width, height, delay, frequency (read by the whole-number helper), page (cleaned), in INSERT/UPDATE order; the screens `array_merge()` the user id (and the edit id) after it. INSERT and UPDATE use `db_q()`.

## What stays in the screens?
- Create: the INSERT, the log, the preview link in the notice, the redirect.
- Edit: the id gate and not-found guard (`panel_id_gate_test.php` reads it), the record load (joined to `user` for the last change, columns prefixed `auto_dialogs.`) and prefill, the preview URL, delete, the UPDATE, the log, the redirects. The id comes from `$_REQUEST` (the template has no hidden id field); the POST branch uses the gated `$id`.

## Why are the numbers checked on the server?
- The columns are INT UNSIGNED and the session `sql_mode` is not strict: before #5701 '-24' hours became 0 (one-time), '1.5' became 2, 99999999999 seconds 4294967295.
- The fields had a decimal mask (minus and decimals allowed), then the integer mask, which dropped a typed `.` and `-` before the save saw them: 1.5 hours was stored as 15 and -5 seconds as 5 (#9014). They now take no mask (maxlength 5, 5, 9, 7: the largest value with its separators), so the save refuses the typed text with its message (`tests/auto_dialog_number_boxes_test.php`).
- Limits: width/height 9999 px; delay 2147483 s, because `frontend.src.js` hands `delay * 1000` to `setTimeout`, which opens at once past 2^31 - 1 ms; frequency 999999 h. The name box takes 100 characters, as its column.

## What does the list's Enabled column say?
- The `task_alt` icon is `aria-hidden` with a `visually-hidden` Yes; a disabled dialog gets a hidden No (#5705), so a screen reader and the DataTables search read Yes/No, not `task_alt`.

## Which test covers it?
- `tests/auto_dialog_form_test.php`: the column order and sticky offsets, the Summary rows, the last-change load, the page clean-up, the value order with a stub liveform, both screens on the helpers with no copy of the checks, the whole-number reading and limits, the masks and the Enabled cell.
