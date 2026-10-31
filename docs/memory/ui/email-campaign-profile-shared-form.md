---
name: email-campaign-profile-shared-form
description: The campaign profile create and edit screens share includes/forms/email_campaign_profile_form.php
---

## Where is the campaign profile form?
- `includes/forms/email_campaign_profile_form.php` (gate `PG_FUNCTIONS_DIR`), PR #2356. `add_email_campaign_profile.php` and `edit_email_campaign_profile.php` stay at the root as thin wrappers and `require_once` it.
- `pg_email_campaign_profile_form_cards($liveform, $user, $product_id, $keep_pages)` prints the five cards (edit passes the stored custom form and body page as `$keep_pages`, so a page in an archived folder stays listed and Save is not refused); `…_form_validate($liveform, $profile_id)` marks the errors (0 = new profile, else its own name is no duplicate); `…_form_columns($liveform)` returns the column values, saved with `db_q()`.

## What stays in the screens?
- Create: the plain text footer prefill of the body, the Create button, the redirect back to the create screen on error.
- Edit: the not-found guard, the record load, the role-3 owner check, the prefill, the Duplicate link, delete, the redirect back with `?id=` on error.

## Where do action rules live?
- `pg_email_campaign_profile_commerce_actions()` lists the actions that need commerce access; `…_action_item_field()` names the field stored in `action_item_id`; `…_action_rows()` which rows open; `…_action_values()` the `action_item_id` / `schedule_period` / `schedule_base` saved (only calendar event reserved takes period and base from the form; order actions save item `0`). A new action is added there, not in the screens. Tests: `tests/email_campaign_profile_form_test.php`.

## Why are the From defaults passed raw?
- On the create screen From Name defaults to `ORGANIZATION_NAME` and From/Reply-to E-mail to the user's address, passed to liveform raw: `output_field()` escapes `value` itself (`core/output-escaping.md`). They used to go through `h()` first, so "Ali & Veli" showed and saved as "Ali &amp; Veli" and went out as the From name of every campaign the profile made; an address with `&` or an apostrophe failed validation. PR #3508, `tests/email_campaign_profile_from_defaults_test.php`.

## Why can a profile not follow itself or a profile that follows it?
- Each profile has one action, so a "campaign sent" profile that follows itself or a ring (A follows B, B follows A) has nothing that starts it; campaigns of it already queued fired again after every run (each finished campaign calls `create_auto_email_campaigns('email_campaign_sent', its profile id)`), one mail per interval to the same contact without end.
- `pg_email_campaign_profile_follow_map()` reads id => action_item_id of every `email_campaign_sent` profile, whatever the user may see. `…_downstream()` (the profile and all that follow it) and `…_closes_ring()` walk it with a visited guard.
- The edit screen passes its id to `…_form_cards()`; `get_email_campaign_profile_options($exclude_ids, $keep_id)` leaves the downstream profiles out but keeps the stored pick listed, so a stored self-loop still shows. Validate refuses a ring on the field. Runtime sending and `import_email_campaign_profiles.php` are unchanged. Tests: `tests/email_campaign_profile_form_test.php`.
