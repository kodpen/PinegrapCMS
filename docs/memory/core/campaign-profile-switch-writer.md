---
name: campaign-profile-switch-writer
description: The code that switches e-mail campaign profiles to their flows (MA9e2): the window check on events, the chain writer, a second run, the followers turned off by the general job
module: campaign_profile_switch
---

## Where is it?
- `includes/fn/campaign_profile_switch.php`; tests `tests/campaign_profile_switch_test.php`. The decisions it carries out: [[campaign-profile-flow-switch]].

## How does an event meet the switch?
- The flow's event start box keeps `profile_id` and `switched_at`; `ws_flow_anchor_normalize('event')` keeps both only when both are set, so the start rule carries them and a drawn flow's rule is unchanged.
- `ws_events_start()` asks `ws_events_switch_starts()` for such a rule: `pg_profile_flow_event_starts()` reads the event's `ws_events_in.created_at`, and inside the window whether the profile made an automatic campaign to the event's person (`pg_profile_flow_event_email()`, the address as the e-mail box reads it) from that second less 60 s.
- `pg_profile_flow_switch_starts()` is the pure rule: before S − 60 never, after S + 60 always, in between only when not mailed.

## How is a chain switched?
- `pg_profile_flow_switch_chain()`: `ws_flow_save()` and `ws_flow_publish()` as the owner (`ws_change_viewer_for_id()`), then `UPDATE … SET enabled = 0 WHERE id = ? AND enabled = 1`. No row changed (a disabled head, or turned off meanwhile): the flow is paused. A refused save or publish leaves the chain a profile, with the reason in the report.
- `pg_profile_flow_switch()` runs the dry run with the chains already switched (`pg_profile_flow_switched()`: flows not binned whose draft's start box holds `profile_id`) left out; the report says "is already flow #n" for them. A second run switches nothing twice.

## When do the followers go off?
- `pg_profile_flow_followers_drain()`, from `job.php` every tenth minute while flows run: a follower of a switched chain goes off once no profile above it has a ready or paused campaign (`pg_profile_flow_followers_done()`, pure).
