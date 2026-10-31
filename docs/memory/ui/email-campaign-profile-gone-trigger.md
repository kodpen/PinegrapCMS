---
name: email-campaign-profile-gone-trigger
description: A campaign profile whose trigger item is deleted is marked "(deleted)" in the list and CSV, noted on its edit screen; Delete names its followers
---

## What was the symptom?
- Deleting the calendar event, custom form page, followed profile or (hard-deleted) product a profile is tied to (`action` + `action_item_id`) leaves the profile enabled, but it can never fire. My Campaign Profiles showed an empty Action item cell; a purged product read `name` on a null `db_item()` row (PHP warnings in the list and the CSV export). The edit picker fell back to its placeholder with no reason. Delete on a profile others follow said only that it would be deleted.

## What do the screens do now?
- `includes/forms/email_campaign_profile_form.php`: `pg_email_campaign_profile_action_item_row()` reads the trigger row (null when gone or none stored); `…_action_item_gone()` and `…_action_item_label()` are pure and give the label or "(deleted)". The list and the CSV export use them.
- The edit screen passes `'action_item_gone' => action` in `$keep_pages`; `…_form_cards()` prints a notice above that action's picker.
- Delete's confirmation is `…_delete_confirmation(…_follower_count($id, …_follow_map()))`: two literal keys plus the plain one, naming how many profiles follow it directly.

## What stays the same?
- Delete removes only the profile; followers are not disabled or repointed (that would be a product decision). Sending (`includes/fn/mail.php`) does not look at the trigger row; it skips only an HTML profile whose body page is gone (`email-campaign-profile-shared-form.md`).

## Where are the tests?
- `tests/email_campaign_profile_gone_trigger_test.php`.

Related: docs/memory/ui/email-campaign-profile-shared-form.md
