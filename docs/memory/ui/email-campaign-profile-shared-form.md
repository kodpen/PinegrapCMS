---
name: email-campaign-profile-shared-form
description: The campaign profile create and edit screens share includes/forms/email_campaign_profile_form.php
---

## Where is the campaign profile form?
- `includes/forms/email_campaign_profile_form.php` (gate `PG_FUNCTIONS_DIR`), PR #2356. `add_email_campaign_profile.php` and `edit_email_campaign_profile.php` stay at the root as thin wrappers and `require_once` it.
- `pg_email_campaign_profile_form_cards($liveform, $user, $product_id)` prints the five cards; `…_form_validate($liveform, $profile_id)` marks the errors (0 = new profile, else its own name is no duplicate); `…_form_columns($liveform)` returns the column values, saved with `db_q()`.

## What stays in the screens?
- Create: the plain text footer prefill of the body, the Create button, the redirect back to the create screen on error.
- Edit: the not-found guard, the record load, the role-3 owner check, the prefill, the Duplicate link, delete, the redirect back with `?id=` on error.

## Where do action rules live?
- `pg_email_campaign_profile_commerce_actions()` lists the actions that need commerce access; `…_action_item_field()` names the field stored in `action_item_id`; `…_action_rows()` which rows open; `…_action_values()` the `action_item_id` / `schedule_period` / `schedule_base` saved (only calendar event reserved takes period and base from the form; order actions save item `0`). A new action is added there, not in the screens. Tests: `tests/email_campaign_profile_form_test.php`.
