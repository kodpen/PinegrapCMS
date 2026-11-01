---
name: dashboard-new-switch
description: The new dashboard's switch row on both dashboards, the welcome.php branch, the dashboard_layout row (step 11.0.143), the sign-in upsert and the dashboard_switch action
---

## How does a person reach the new dashboard?
- `welcome.php`, after its access check and the Appearance values, reads the person's `dashboard_layout` row (`pg_dashboard_v2_person_row()`, `includes/dashboard/v2/layout.php`); when `enabled` is 1 it prints `pg_dashboard_v2_page()` (`page.php`) and exits, so nothing of the current dashboard runs. Otherwise the current page draws as before with the switch row (`pg_dashboard_v2_switch_row(false)`) above `.pg-clockbar`: its only change.
- The switch (`assets/js/dashboard_switch.js`, bundle `dashboard_switch.min.js`, loaded on both pages) posts `dashboard_switch {on}` to `api.php` and reloads `welcome.php`. The action is `exempt` with the token checked in the handler (`includes/panel/dashboard.php`), so every backend role may flip its own switch; a failed write (no table yet) answers an error and the switch flips back with a toast.
- The switch row's rules live in `backend.src.css` (the current page draws it too); the new page's own rules in `assets/css/dashboard_v2.src.css` (`pg_css_bundles()`).

## Where is the row kept?
- Table `dashboard_layout` (step `11.0.143`, 2026.11.0): a person's row is `user_id` > 0 with `role` 0; a role default row `user_id` 0 with `role` 0-3 or 9. `role` is NOT NULL: a NULL inside the unique key (`user_id`, `role`) would allow two rows for one person. `layout` is MEDIUMTEXT NOT NULL without a default, so every INSERT writes `''`.
- The decision named `11.0.141`; that label went to the form_type step (#4667) and `11.0.142` is held by the WebP plan, so the step took `11.0.143` (labels are names only).

## How are the sign-in times written?
- `pg_session_sign_in()` (`includes/authentication.php`), the end of every sign-in path, calls `pg_dashboard_sign_in_mark()`: one upsert (`pg_dashboard_sign_in_query()`) that copies `signed_in_at` into `signed_in_before` and writes the new time; MySQL runs ON DUPLICATE KEY assignments left to right. It uses `@mysqli_query(db::$con, …)` because `get_file.php` loads the file without `functions.php`, and before the upgrade the table is missing (upgrade bridge).
- `login_as_user.php` passes `$mark_sign_in = false`: an administrator signing in as someone else must not move that person's "since your last sign-in" time.
- Tests: `tests/dashboard_v2_shell_test.php`.
