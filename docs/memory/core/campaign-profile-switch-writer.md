---
name: campaign-profile-switch-writer
description: The code that switches e-mail campaign profiles to their flows (MA9e2): update step marks due, general job converts, the file in data/, the window on events, the report notice and bell, a second run
module: campaign_profile_switch
---

## Where is it?
- `includes/fn/campaign_profile_switch.php`; tests `tests/campaign_profile_switch_test.php`. The decisions it carries out: [[campaign-profile-flow-switch]].

## Who converts, and when? (architect, 2026-11-14, channel 25 message 3005)
- Step 11.0.206 `upgrade_2026_11_0_campaign_profile_flows()` only marks it due (`pg_profile_flow_switch_mark()`): the upgrade request loads no Workspace code. Written once: not with the file there, a flow already carrying a profile, or no profile; data/ not writable: nothing marked, an `install_note`.
- The file `data/campaign_profile_flows_<md5(database@host)>.php` (not data/temp, which the clean-up sweeps) opens with `<?php exit; ?>` and holds JSON: status due/running/done, `ceiling` (highest profile id at the update), switched, failed, report.
- `job.php` calls `pg_profile_flow_switch_tick()` every tick while the file is there: chain by chain within 20 s, a Site Log line each, the file written after every chain. With none left: status done, the report stored as the plan (`pg_profile_flow_switch_final()`), and one bell line `profile_flows` for role 0.
- What is done lives in the rows: a flow's start box with `profile_id`, the head turned off. A lost file converts nothing more. A profile above the ceiling stays ("It was made after the update.").

## How is a chain switched?
- `pg_profile_flow_switch_chain()`: `ws_flow_save()` and `ws_flow_publish()` as the owner, then `UPDATE … SET enabled = 0 WHERE id = ? AND enabled = 1`; no row changed: the flow is paused. A refused publish deletes the new draft, so the chain stays a profile with the reason.
- `pg_profile_flow_switched()` reads every flow's draft, deleted ones too: a chain is never switched twice.
- A Send an e-mail box to the record's person is checked at publish with a stand-in address (`ws_step_check_stand_in()`); before, every such flow was refused "Write the address to send it to."

## How does an event meet the switch?
- `ws_flow_anchor_normalize('event')` and `ws_scheduled_rule_input()` (`ws_scheduled_event_switch()`) keep `profile_id` and `switched_at` only when both are set, so the anchor's rule carries them.
- `ws_events_start()` asks `pg_profile_flow_event_starts()`: the event's `ws_events_in.created_at`; inside ±60 s whether the profile mailed the event's person (`pg_profile_flow_event_email()`) from that second less 60 s. The pure rule is `pg_profile_flow_switch_starts()`.

## Where is the report?
- E-mail Campaign Profiles screen: `pg_profile_flow_switch_notice()`, the panel's alert (`id="profile-flows"`); due or running: under way; done: "Profiles turned into flows on <date>" opening to two lists: the flows made, each linked to its flow, and the profiles left, each linked to its profile, as `pg_profile_flow_report_lines()` words them in the reader's language, actions by the screen's names (architect, 2026-11-14).
- Also on the Automatic sends tab, where it stays once the profiles screen leaves; the screens after the switch and the reasons kept as lang() specs: [[campaign-profile-screens-left]] (MA9f).

## When do the followers go off?
- `pg_profile_flow_followers_drain()`, `job.php` every tenth minute while flows run: once no profile above it has a ready or paused campaign.
