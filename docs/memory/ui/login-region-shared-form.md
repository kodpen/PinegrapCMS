---
name: login-region-shared-form
description: The login region create and edit screens share includes/forms/login_region_form.php: what it holds, the default, what stays in the screens
---

## Where is the login region form?
- `includes/forms/login_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_login_region.php` and `edit_login_region.php` stay at the root as thin wrappers and `require_once` it; both are designer-only (`validate_area_access($user, 'designer')`).
- `pg_login_region_form_card($liveform, $defaults = false)` prints the Options card: the name between `<login>` tags, the Not Logged In header, Show Login Form switch and footer, the Logged In header and footer (code editors).
- `$defaults` (create) checks Show Login Form.

## What does a save share?
- `pg_login_region_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required, then, only when a name was given, not used by another login region. The screen sends the user back once if either check fails (before, each check had its own redirect to the same screen).
- `pg_login_region_form_values($liveform)`: the six values in INSERT/UPDATE order. The screens `array_merge()` their user ids (and the edit id) after it.

## What stays in the screens?
- Create: the INSERT, the notice on the login region list.
- Edit: the not-found guard, the record load, the body tag line, delete, the UPDATE, the notice.
- Tests: `tests/login_region_form_test.php` (value order with a stub liveform, the default, both screens on the form); `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the queries of the screens and the form.
