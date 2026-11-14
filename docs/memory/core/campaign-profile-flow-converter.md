---
name: campaign-profile-flow-converter
description: Campaign profiles into flows, the converter and dry-run report (MA9d, #9458): profile to boxes, when a chain stays, owner, limits, tools/campaign_profile_flow_report.php
module: campaign_profile_flow
---

## Where is it?
- `includes/fn/campaign_profile_flow.php`: pure `pg_profile_flow_*()` functions; `pg_profile_flow_facts()` and `pg_profile_flow_dry_run()` read the rows (nothing written) and need the Workspace's flow modules loaded.
- `php tools/campaign_profile_flow_report.php [--json]`: prints the report of the site its checkout's `data/config.php` names (a copy of a running site for the owner's look). MA9e writes the same lines into its result in the panel.
- Tests: `tests/campaign_profile_flow_test.php` (each built graph passes `ws_flow_graph_check()`; the starting sites' profiles as worked out by hand).

## Which box does a profile become?
- Start: `pg_profile_flow_event()` (the map in [[campaign-profile-flow-map]]); a form's page as `page_id`, a group as `status`; a calendar event a Condition `{{trigger.record_id}}` eq the event (box `calendar`).
- Wait (`pg_profile_flow_wait()`): none for length 0 and any time; `for` its length in days or hours, time of day the send time (00:00:00 none). Calendar start base: `until {{trigger.start_at}}` less the length, `passed` end.
- Stays: a wait over `WS_FLOW_DELAY_MAX`, before its action, after a calendar event's start, a send time before the start (the Wait box moves a moment on, never back).
- E-mail (`pg_profile_flow_email()`): the record's person, commercial → marketing, plain text or the page, the sender carried ([[workspace/workspace-flows-email-campaign]]).
- Follower: after its leader's `mail_<id>` a Condition `{{node.mail_<leader>.sent}}` gte 1 (`sent_<id>`), its Wait (`wait_<id>`), its e-mail (`mail_<id>`).

## When does a chain stay whole?
- Any profile of it with: no start for its action, a wait no box carries, a `^^field^^` that neither its event's start outputs nor the contact (name, first_name, last_name) fill (`pg_profile_flow_fields_missing()`; the page's text read from `pregion`), its page gone, a limit, or the graph over `WS_FLOW_MAX_NODES`. Its partners say "A profile of its chain stays: <name>."
- A follower of a gone profile, or followers in a ring, send nothing today: listed, never converted.

## Owner and limits?
- `pg_profile_flow_owner()`: the head's creator while enabled, role 0-2 and holding the event's right (`ws_events_catalog()` right), else the first enabled role 0 account, with the reason.
- `pg_profile_flow_limit_reason()`: the head's busiest hour of 30 days over 80% of min(`WS_FLOW_STARTS_HOURLY`, `WS_SCHEDULED_CHAIN_HOURLY`), or open runs (`pg_profile_flow_open_runs()`: 30 days' campaigns spread per minute × the chain's waits added up, a send time counted as a day) over 80% of `WS_FLOW_MAX_OPEN_RUNS`.

## What does the report say?
- `pg_profile_flow_report_lines()`: counts; per flow its name, state (a disabled head: paused), owner and why it changed, one line per box (`pg_profile_flow_box_line()`), per profile its last 30 days (automatic campaigns, busiest hour, ready or paused ones left to the campaign job, for commercial the recipients no contact holds); per profile left its name, action, state and reason.
- Sandbox check (2026-11-14, `turkish_default`): "Kargo Yola Çıktı" becomes a paused flow, "Sipariş Yardımı" stays (no start), as [[campaign-profile-flow-dry-run]] worked out by hand.
