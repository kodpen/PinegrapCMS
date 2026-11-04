---
name: login-region-shared-form
description: The login region create and edit screens share includes/forms/login_region_form.php: the columns, the Summary, the save bar, the default, what stays in the screens
---

## Where is the login region form?
- `includes/forms/login_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_login_region.php` and `edit_login_region.php` stay at the root as thin wrappers and `require_once` it; both are designer-only (`validate_area_access($user, 'designer')`).
- `pg_login_region_form_card($liveform, $defaults = false)` prints the form column (`col-12 lg:col-8 2xl:col-9 order-1`) with the Options card: the name between `<login>` tags, the Not Logged In header, Show Login Form switch and footer, the Logged In header and footer (code editors).
- `pg_login_region_form_status($liveform, $defaults, $status_rows)` prints the sticky status column (`top:3.3rem`) with the Summary: the page style body tag `<login>name</login>` and Login Form Shown/Hidden, both following the boxes as they change, then the screen's rows (`pg_login_region_form_status_row()`). `pg_login_region_form_layout($columns, $buttons)` wraps both in the row with the sticky save bar (B11 redesign, `ui/edit-screen-wide-layout.md`).
- The body tag line left the edit screen's toolbar for the Summary, so the edit screen draws no toolbar (`tests/screen_lone_controls_test.php` skips it).
- `$defaults` checks Show Login Form. Create passes `$liveform->prefill()`, so only a first draw shows the default; with `true` a create refused for its name turned the switch back on and the next Create saved `login_form = 1`.

## What does a save share?
- `pg_login_region_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required, then, only when a name was given, not used by another login region. The screen sends the user back once if either check fails (before, each check had its own redirect to the same screen).
- `pg_login_region_form_values($liveform)`: the six values in INSERT/UPDATE order. The screens `array_merge()` their user ids (and the edit id) after it.

## What stays in the screens?
- Create: the INSERT, the notice on the login region list.
- Edit: the not-found guard, the record load (with the last change and who made it, a dash when the timestamp is 0), the Last Modified Summary row, delete, the UPDATE, the notice.
- Tests: `tests/login_region_form_test.php` (value order with a stub liveform, the default, both screens on the form, the columns and the Summary); `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the queries of the screens and the form.
