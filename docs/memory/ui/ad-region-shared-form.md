---
name: ad-region-shared-form
description: The ad region create and edit screens share includes/forms/ad_region_form.php: what it holds, the defaults, what stays in the screens
---

## Where is the ad region form?
- `includes/forms/ad_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_ad_region.php` and `edit_ad_region.php` stay at the root as thin wrappers and `require_once` it.
- `pg_ad_region_form_card($liveform, $name_field, $defaults = false)` prints the Options card: the name between `<ad>` tags, display type, transition type and duration, autoplay with interval and continuous play.
- `$name_field` is the name markup. Create passes a plain `<input>` (not a liveform field, so a name refused on submit comes back empty, as before). Edit passes a liveform field for a designer and the stored name as text for a manager, who may not rename.
- `$defaults` (create) checks Static, Slide and Play Continuously.

## What does a save share?
- `pg_ad_region_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit): required, the tag name rule (`tag_name_rule.php`, shared with the menu form), not used by another region. Edit runs it only for a designer.
- `pg_ad_region_form_values($liveform)`: the six behaviour values in INSERT/UPDATE order. Create puts the name before them; edit puts it there only for a designer.

## What stays in the screens?
- Create: the INSERT, the notice on the region list.
- Edit: the not-found guard, the user role's access check, whether ads are assigned (delete is refused while they are), the record load, the body tag line, delete with `users_ad_regions_xref`, the UPDATE, `send_to`.
- Tests: `tests/ad_region_form_test.php`, `tests/tag_name_rule_test.php`; `bs6_form_adorn_test.php`, `db_q_pages_regions_test.php` and `db_q_regions_menus_calendars_test.php` read the screens and the form.
