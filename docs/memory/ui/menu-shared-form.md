---
name: menu-shared-form
description: The menu create and edit screens share includes/forms/menu_form.php: what it holds, the underscore name rule, what stays in the screens
---

## Where is the menu form?
- `includes/forms/menu_form.php` (gate `PG_FUNCTIONS_DIR`). `add_menu.php` and `edit_menu.php` stay at the root as thin wrappers and `require_once` it.
- `pg_menu_form_card($liveform)` prints the Menu Options card: name between `<menu>` tags, submenu effect with its pop-up positions, the menu classes and the active item classes (`…_class_field()` draws each classes field).

## What does a save share?
- `pg_menu_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required, the name rule, not used by another menu.
- The rule is `pg_tag_name_error($name, $previous_name)` in `includes/forms/tag_name_rule.php`, shared with the ad region form: letters, numbers and dashes. On edit, underscores stay allowed only when the stored name already has one, because the theme designer does not support them. Create and edit keep their own wording ("and dashes" / "or dashes").
- `pg_menu_form_values($liveform)`: the six column values in INSERT/UPDATE order; the screens add the user ids (and the id on edit).

## What stays in the screens?
- Create: the default effect (Pop-up, Bottom, Right), the INSERT, the notice on the menu list.
- Edit: the not-found guard, the record load, the body tag line and Duplicate link, `from` / `send_to`, delete (menu, items, `users_menus_xref`), the UPDATE, the redirect to the menu items.
- Tests: `tests/menu_form_test.php`, `tests/tag_name_rule_test.php`; `db_q_pages_regions_test.php` and `db_q_regions_menus_calendars_test.php` read the screens and the form.
