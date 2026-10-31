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
- `pg_email_campaign_profile_commerce_actions()` lists the actions that need commerce access; `…_action_item_field()` names the field stored in `action_item_id`; `…_action_rows()` which rows open; `…_action_values()` the `action_item_id` / `schedule_period` / `schedule_base` saved (only calendar event reserved takes period and base from the form; order actions save item `0`). A new action is added there, not in the screens. Follow rings: `email-campaign-profile-follow-ring.md`. Tests: `tests/email_campaign_profile_form_test.php`.

## Why are the From defaults passed raw?
- On the create screen From Name defaults to `ORGANIZATION_NAME` and From/Reply-to E-mail to the user's address, passed to liveform raw: `output_field()` escapes `value` itself (`core/output-escaping.md`). They used to go through `h()` first, so "Ali & Veli" showed and saved as "Ali &amp; Veli" and went out as the From name of every campaign the profile made; an address with `&` or an apostrophe failed validation. PR #3508, `tests/email_campaign_profile_from_defaults_test.php`.

## What happens when a profile create, save or delete fails?
- The three `db_q()` writes in `add_email_campaign_profile.php` and `edit_email_campaign_profile.php` end with `or output_error('Query failed.')`. After their move from `db()` to `db_q()` they dropped the `false` and still logged and announced "created/saved/deleted" (fixed in #4132). Pin: `test_email_campaign_profile_writes_are_checked`.

## Which forms does the Custom Form picker list?
- `get_page_options('', 'any form', 'edit', false, $keep)`: `'any form'` is `pg_form_page_sql()`, the Forms screen set (classic custom form pages and visual-designer forms). Pages in the bin, in a binned folder or in an archived folder are left out; the stored form stays, marked. Before, only `page_type = 'custom form'` was listed, so a designer-built form could not be picked (#4030, PR #TBD).

## How is a binned or deleted page shown?
- `pg_page_record_label($page_id, $row, $binned)` / `pg_page_record_name($page_id)` (`includes/fn/content.php`): the name, the former name + ' [IN RECYCLE BIN]' (`pg_recycle_parked_original()`, the same as `get_page_options()`), '(deleted)' when the row is gone. The list and the CSV use it for the body page and the form trigger; before, they printed `pgbin_<id>_…` or nothing.
- The edit screen passes `'page_gone'` in `$keep_pages`: a notice above the Page picker. `create_auto_email_campaigns()` skips an HTML profile whose page row is gone with a Site Log line instead of queuing a body without the page (#4033, PR #TBD).
