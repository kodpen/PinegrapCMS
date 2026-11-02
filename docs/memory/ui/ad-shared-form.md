---
name: ad-shared-form
description: The ad create and edit screens share includes/forms/ad_form.php: what it holds, what stays in the screens
---

## Where is the ad form?
- `includes/forms/ad_form.php` (gate `PG_FUNCTIONS_DIR`). `add_ad.php` and `edit_ad.php` stay at the root as thin wrappers and `require_once` it.
- `pg_ad_form_cards($liveform)` prints both cards: Options (name, ad region, content) and Dynamic Ad Region Display Options (label, sort order, caption). The two screens drew the same markup, so the form takes no switches.

## What shows when no ad region exists?
- Neither starter dump creates an ad region, so on a fresh site the required select held only a blank entry and Create stopped on a bare "required" error. `pg_ad_form_region_hint($options, $role)` prints a line under the select when `get_ad_region_options()` holds only its blank entry: role 0–1 get "No ad region exists yet." with a link to Create Ad Region (`add_ad_region.php`, designer-only); other roles are told a designer creates ad regions. The cards read the role from `global $user`, as the options do; role 3 with no region never reaches the form (the screens refuse it).

## What does a save share?
- `pg_ad_form_validate($liveform, $user, $id)` (`null` on create, the posted id on edit): name and ad region required, the name not used by another ad (edit leaves its own id out), and a user role (3) may only pick an ad region it can edit.
- Sort Order is blank (random order) or a whole number of up to four digits (`pg_ad_form_sort_order_error()`, bug hunt #4854): the column is an integer, and before the check "abc", "1.5" or "-3" were saved as 0 or cut, so the ad silently went back to random order.
- `pg_ad_form_values($liveform, $path = null)`: name, content, caption, ad region, label, sort order in INSERT/UPDATE order; content and caption go through `prepare_rich_text_editor_content_for_input()` (site path stored as `{path}`). The screens `array_merge()` their user ids (and the edit id) after it.

## What stays in the screens?
- Create: the screen's role-3 gate (no editable region, no access), the INSERT, the notice on the ad list.
- Edit: the not-found guard, the role-3 access check on the ad's current region, the record load (sort order 0 shown empty), the hidden id and `send_to`, delete, the UPDATE, `send_to` or the notice.
- Tests: `tests/ad_form_test.php` (value order with a stub liveform, the sort order rule, both screens on the form, the empty-region hint per role); `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the queries of the screens and the form.
