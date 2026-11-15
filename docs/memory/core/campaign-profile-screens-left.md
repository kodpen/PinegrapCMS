---
name: campaign-profile-screens-left
description: Campaign profile screens after the switch to flows (MA9f): only profiles left, converted ones lead to their flow, screens leave with none, report on Automatic sends, reasons as specs
---

## Which profiles do the screens show?
- Only the profiles left: `pg_profile_flow_converted()` (includes/fn/campaign_profile_switch.php) is every profile of a chain a flow was made of, head and followers (`pg_profile_flow_members()`), with its flow; `pg_profile_flow_left_sql()` leaves them out of My Campaign Profiles, its Export, and the Automatic sends tab's count "N campaign profile(s) still send as before" (`pg_messages_profiles_running()`).
- Import Campaign Profiles does not write over a converted profile: its row is listed "This profile became a flow; change the flow instead."

## Where does a profile's address lead?
- `pg_profile_flow_screen_go()` at the top of `view_`, `add_`, `edit_`, `duplicate_email_campaign_profile(s).php` and `import_email_campaign_profiles.php` (`pg_profile_flow_screen_door()`, pure):
- edit or duplicate of a converted profile: to its flow, `workspace_flows.php?flow=<id>`;
- any of them on a site with no profile left (all converted, or none ever made): to the Automatic sends tab, `messages_automatic.php`. A site with none can no longer make a profile: M2b, the profile screens go.

## Where is the conversion's report?
- On the E-mail Campaign Profiles screen while it stays, and on the Automatic sends tab always (`pg_profile_flow_switch_notice()`, actions by `pg_profile_flow_action_names()`), so it stays reachable once the profiles screen leaves (architect, 2026-11-14).

## How is a reason kept?
- Untranslated, as the lang() spec it is drawn from (`array('string' => …, 'vars' => …)`): the converter's reasons, the wait refusals, the limits, "It was made after the update.", a chain partner's line. `pg_profile_flow_reason_text()` draws it in the reader's language, so a report the general job stored reads in whoever opens it's language.
- A plain string is shown as it is: the flow code's own refusal when a publish fails (in the job's language), and reports stored before MA9f.
- Tests: `tests/campaign_profile_switch_test.php` (members, the door, the left SQL, the reason specs, each screen calling the door).

## What is still open?
- The six actions with no flow start (order abandoned, review request, product ordered, booking reminder, credit expiring, subscription price changed): architect (2026-11-13, #9077), a later plan, written once the dry-run report on running sites shows which are in use ([[campaign-profile-flow-gaps]]). M2b is complete on a site when no profile is left there.
