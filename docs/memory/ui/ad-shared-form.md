---
name: ad-shared-form
description: The ad create and edit screens share includes/forms/ad_form.php: what it holds, what stays in the screens
---

## Where is the ad form?
- `includes/forms/ad_form.php` (gate `PG_FUNCTIONS_DIR`). `add_ad.php` and `edit_ad.php` stay at the root as thin wrappers and `require_once` it.
- The screens follow the wide layout (`edit-screen-wide-layout.md`, step B11): `pg_ad_form_cards($liveform)` is the form column (Options: name, content; Dynamic Ad Region Display Options: label, sort order, caption); `pg_ad_form_status($liveform, $rows)` the sticky status column with the Ad Region card (the select, its hint) and a Summary of the chosen region (`pg_ad_form_region_label()`, a dash when none) and the sort order (`pg_ad_form_sort_order_label()`, blank reads Random), followed live; edit adds Last Modified with the user (`pg_ad_form_status_row()`, read in the record's one query, a dash for a row with no time); `pg_ad_form_layout($columns, $buttons)` the row and the sticky save bar.

## What shows when no ad region exists?
- Neither starter dump creates an ad region, so on a fresh site the required select held only a blank entry and Create stopped on a bare "required" error. `pg_ad_form_region_hint($options, $role)` prints a line under the select when `get_ad_region_options()` holds only its blank entry: role 0–1 get "No ad region exists yet." with a link to Create Ad Region (`add_ad_region.php`, designer-only); other roles are told a designer creates ad regions. The cards read the role from `global $user`, as the options do; role 3 with no region never reaches the form (the screens refuse it).

## What does a save share?
- `pg_ad_form_validate($liveform, $user, $id)` (`null` on create, the posted id on edit): name and ad region required; the name at most 100 characters (`pg_name_too_long()`) and not used by another ad (edit leaves its own id out); the chosen region still in `ad_regions` for every role; a user role (3) may only pick an ad region it can edit.
- Why the length and region checks (#5748, #5749): the session is not strict, so a 101-character name was cut to an existing ad's name after the clash check passed; a region deleted while the form was open (or a made-up id) saved an ad no page shows, listed with no region. The ad list shows no Display Type for such an ad, not Dynamic.
- Sort Order is blank (random order) or a whole number of up to four digits (`pg_ad_form_sort_order_error()`, bug hunt #4854): the column is an integer, and before the check "abc", "1.5" or "-3" were saved as 0 or cut, so the ad silently went back to random order.
- `pg_ad_form_values($liveform, $path = null)`: name, content, caption, ad region, label, sort order in INSERT/UPDATE order; content and caption go through `prepare_rich_text_editor_content_for_input()` (site path stored as `{path}`). The screens `array_merge()` their user ids (and the edit id) after it.

## What stays in the screens?
- Create: the screen's role-3 gate (no editable region, no access), the INSERT, the notice on the ad list.
- Edit: the not-found guard, the role-3 access check on the ad's current region, the record load (sort order 0 shown empty), the hidden id and `send_to`, delete, the UPDATE, `send_to` or the notice.
- Tests: `tests/ad_form_test.php` (value order with a stub liveform, the wide layout, the sort order rule, both screens on the form, the empty-region hint per role); `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the queries of the screens and the form.

## What does the Ad Region select show for two regions with one name?
- `get_ad_region_options()` builds its list with `pg_ad_region_option_list($rows)` (`includes/fn/forms.php`). The label is the array key, so a second region with a name already listed is shown as `name (#id)` instead of replacing the first. New names cannot clash (100-character cap, #5888); this covers regions stored before it (#5748).
