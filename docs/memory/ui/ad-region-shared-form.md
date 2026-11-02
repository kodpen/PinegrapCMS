---
name: ad-region-shared-form
description: The ad region create and edit screens share includes/forms/ad_region_form.php: what it holds, the defaults, what stays in the screens
---

## Where is the ad region form?
- `includes/forms/ad_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_ad_region.php` and `edit_ad_region.php` stay at the root as thin wrappers and `require_once` it.
- `pg_ad_region_form_card($liveform, $name_field, $defaults = false)` prints the Options card: the name between `<ad>` tags, display type, transition type and duration, autoplay with interval and continuous play.
- `$name_field` is the name markup. Create passes a liveform field (required, 100 characters), so a refused name comes back as typed. Edit passes a liveform field for a designer and the stored name as text for a manager, who may not rename.
- `$defaults` checks Static, Slide and Play Continuously. Create passes `$liveform->prefill()`: the defaults show only on a first draw. A box the user unticked is not posted, so it is not in the session and liveform would fall back to the default; with `true` a refused create came back with Play Continuously ticked again.

## What does a save share?
- `pg_ad_region_form_validate_name($liveform, $id)` (`null` on create, the posted id on edit) marks `pg_ad_region_name_error()`: required, at most 100 characters, the tag name rule (`tag_name_rule.php`, shared with the menu form; its messages say "English letters (a-z)"), not used by another region. Edit runs it only for a designer. A longer name was cut by the non-strict session into an existing region's name (#5748).
- `pg_ad_region_form_validate_behavior($liveform)` marks `pg_ad_region_behavior_errors()`, run by both screens for every role: Display Type static/dynamic; for a dynamic region Transition Type slide/fade and Duration blank or 0–9999; with autoplay on, Interval blank or 1–999 whole seconds. Both number boxes use the integer mask without a minus (#5747: `-250` and `2,5` → 3 were saved).
- `pg_ad_region_form_values($liveform)`: the six behaviour values in INSERT/UPDATE order, each forced to an allowed value (a hidden, unchecked field is written as its default). Create puts the name before them; edit puts it there only for a designer.

## What stays in the screens?
- Create: the INSERT, the notice on the region list.
- Edit: the not-found guard, the user role's access check, whether ads are assigned (delete is refused while they are), the record load, the body tag line, delete with `users_ad_regions_xref`, the UPDATE, `send_to`.
- Tests: `tests/ad_region_form_test.php`, `tests/tag_name_rule_test.php`; `bs6_form_adorn_test.php`, `db_q_pages_regions_test.php` and `db_q_regions_menus_calendars_test.php` read the screens and the form.
