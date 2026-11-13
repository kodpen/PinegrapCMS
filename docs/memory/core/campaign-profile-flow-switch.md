---
name: campaign-profile-flow-switch
description: How e-mail campaign profiles become flows with no mail lost or sent twice (MA9a, #NNNN): one flow per chain, the switch moment, campaigns already waiting, followers drained, the owner
---

## One flow of what? (worker, 2026-11-13, #NNNN)
- One flow per chain: a profile started by an action, then each profile following it (`email_campaign_sent`, a tree) as boxes after its leader's e-mail box: a Condition `{{node.<leader>.sent}}` at least 1 (a leader skipped for consent makes no campaign, so no follow-up today), a Wait, the follower's e-mail box. Over `WS_FLOW_MAX_NODES` boxes the chain stays.
- A chain converts whole or stays whole: a follower left behind would never fire once its leader stops making campaigns.
- The flow takes the head's name; a disabled head becomes a paused flow; a chain whose head follows a deleted profile sends nothing today and is listed, not converted.
- Owner: the profile's creator while on the staff and holding the start's right (orders, contacts, forms…), else the first active administrator (role 0, lowest id), named in the report. A flow's boxes act with their owner's rights (`ws_flow_step_run()`); a profile never asked for any.

## What already waits?
- An automatic campaign a profile made is a whole `email_campaigns` row (body, time, recipient): the campaign job sends it as today, the flow never takes it.
- Its follow-ups still come from the follower profiles: they stay enabled after the switch and fire only on campaigns made before it (the head makes no more). A job pass turns a follower off once no ready or paused campaign of a profile above it is left.

## The switch moment?
- Per chain, one request: the flow is published; the head is turned off by `UPDATE email_campaign_profiles SET enabled = 0 WHERE id = ? AND enabled = 1` (its affected row says this run did it) at second S; the flow's event start box keeps `profile_id` and `switched_at` = S in its settings (no column).
- A `ws_events_in` row created before S − 60 s never starts that flow: the profile served its action. After S + 60 s it always does. In between it starts only when the profile made no automatic campaign to the event's person from the event's second less 60 s on (`email_campaigns.email_campaign_profile_id`, its recipient).
- Why the window: one request may run `create_auto_email_campaigns()` before or after `pg_event_record()` (a booking announces first, an order calls the profiles first), so a boundary on either alone loses or doubles the mail of a request caught at S.
- Why the event's own time: rows wait in `ws_events_in` until the job takes them, and every active start box matches them then (`ws_events_start()`), so a flow published at S would also start for older rows.
- Flows run with the Workspace off first (AF1 to AF3): until then `pg_event_record()` records nothing on such a site.

## Twice by a retry?
- No: an event start is keyed `sched:<ws_scheduled_runs id>`, and the e-mail box writes `began:email` before it sends, so a stale box fails instead of sending again (`workspace/workspace-flows-steps.md`).
- What no box covers: [[campaign-profile-flow-gaps]]; the report: [[campaign-profile-flow-dry-run]].
