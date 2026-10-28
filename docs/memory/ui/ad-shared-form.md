---
name: ad-shared-form
description: The ad create and edit screens share includes/forms/ad_form.php: what it holds, what stays in the screens
---

## Where is the ad form?
- `includes/forms/ad_form.php` (gate `PG_FUNCTIONS_DIR`). `add_ad.php` and `edit_ad.php` stay at the root as thin wrappers and `require_once` it.
- `pg_ad_form_cards($liveform)` prints both cards: Options (name, ad region, content) and Dynamic Ad Region Display Options (label, sort order, caption). The two screens drew the same markup, so the form takes no switches.

## What does a save share?
- `pg_ad_form_validate($liveform, $user, $id)` (`null` on create, the posted id on edit): name and ad region required, the name not used by another ad (edit leaves its own id out), and a user role (3) may only pick an ad region it can edit.
- `pg_ad_form_values($liveform, $path = null)`: name, content, caption, ad region, label, sort order in INSERT/UPDATE order; content and caption go through `prepare_rich_text_editor_content_for_input()` (site path stored as `{path}`). The screens `array_merge()` their user ids (and the edit id) after it.

## What stays in the screens?
- Create: the screen's role-3 gate (no editable region, no access), the INSERT, the notice on the ad list.
- Edit: the not-found guard, the role-3 access check on the ad's current region, the record load (sort order 0 shown empty), the hidden id and `send_to`, delete, the UPDATE, `send_to` or the notice.
- Tests: `tests/ad_form_test.php` (value order with a stub liveform, both screens on the form); `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the queries of the screens and the form.
