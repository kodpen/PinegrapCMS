---
name: email-campaign-profile-follow-ring
description: A campaign profile may not follow itself or a profile that follows it, on the edit screen and in the import
---

## Why can a profile not follow itself or a profile that follows it?
- Each profile has one action, so a "campaign sent" profile that follows itself or a ring (A follows B, B follows A) has nothing that starts it; campaigns of it already queued fired again after every run (each finished campaign calls `create_auto_email_campaigns('email_campaign_sent', its profile id)`), one mail per interval to the same contact without end.
- `pg_email_campaign_profile_follow_map()` reads id => action_item_id of every `email_campaign_sent` profile, whatever the user may see. `…_downstream()` (the profile and all that follow it) and `…_closes_ring()` walk it with a visited guard.
- The edit screen passes its id to `…_form_cards()`; `get_email_campaign_profile_options($exclude_ids, $keep_id)` leaves the downstream profiles out but keeps the stored pick listed, so a stored self-loop still shows. Validate refuses a ring on the field. Runtime sending is unchanged. Tests: `tests/email_campaign_profile_form_test.php`.
- Import Campaign Profiles runs `…_follow_problem()` (missing profile, then `…_closes_ring()`) on a row whose resulting action is `email_campaign_sent` when the file has an `action` or `action_item_id` column, and updates the follow map after each row written. A refused row is not written and is named 'Line n: reason' (`pg_csv_problem_list()`). Before, a CSV row could make a profile follow itself (#4031, PR #TBD).
