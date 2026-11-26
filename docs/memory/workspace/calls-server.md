---
name: calls-server
description: Workspace calls on the server (CL1, #10499, step 11.0.243): tables, start/join/leave/end, signals read by their member only, the 2-minute end, who may, limits, read-only API
---

## What do the tables hold? (step 11.0.243)
- `ws_calls`: channel_id, started_by (a user id), kind voice|video, state live|ended, started_at, ended_at. One live call a channel: a start where one is on joins it.
- `ws_call_members`: one row per join (user_id or guest_id), joined_at, left_at (0 while in the call). A party already in the call is refused a second row; its Join here takes the old place over (`calls-endurance.md`).
- `ws_call_signals`: from_member_id, to_member_id, kind offer|answer|candidate|ping|share|unshare|hand|unhand, body (at most 32 KB), created_at. A ping is the keep-alive; it and the share and hand marks (`calls-share.md`, `calls-hand.md`) go to nobody.
- No media, recording or transcript is stored (`workflow/decisions-made-schema-29.md`).

## Who may take part?
- A person with a membership row in the channel: reading a public channel is not enough. A direct conversation is a private channel of its two people.
- A guest only in its own room (`ws_guests.channel_id`), joining, never starting; a guest a channel was shared with to read only does not join.
- No call in an archived channel, a mailbox room or a discussion. Four people at most (`WS_CALL_MAX_MEMBERS`): the fifth gets "This call is full."
- Ending for everyone: the starter or whoever `ws_can_manage_channel()`; others leave. The last one out ends the call.
- Taken out of the channel during a call (or a guest's link turned to read only, the channel archived): the next read or signal takes the place out (`ws_call_party_out_if_barred()`); out of a private channel, the refused read does (#11765).

## How are signals read?
- Only by their addressee: `ws_call_signals_for()` reads `to_member_id` of the reader's own live row, after the id it holds (`call_after`). Reading deletes nothing.
- They ride the live reads (`calls-signalling.md`): `ws_sync` answers `call` (call, member_id, signals); the guest page's `state` the same.

## When does a call end and what is cleaned?
- A member unheard for 90 s (`WS_CALL_MEMBER_SILENCE`, no signal of its own) is taken out by the clean-up (`calls-endurance.md`).
- Left by everyone, ended by its starter or a manager, or silent: no start, join or signal for 120 s (`WS_CALL_SILENCE`); a silent call ends at the time it was last heard (`ws_call_heard_at()`, #11765). Then every member is out and its signals are deleted. The channel gets its ended, missed or declined line (`calls-lines.md`).
- Any signal older than 120 s is deleted. The clean-up (`ws_calls_sweep()`) runs from the live reads while the stamp exists, at most every 10 s, and on every start.

## What are the limits?
- 3 calls started a minute per person (`WS_CALL_STARTS_A_MINUTE`); 300 signals a minute per member (`WS_CALL_SIGNALS_A_MINUTE`), counted on the kept signals.

## Which actions and routes are there?
- Panel: `ws_call_start` (channel_id, kind), `ws_call_join` (channel_id, call_id), `ws_call_leave` (call_id, member_id), `ws_call_end`, `ws_call_signal` (call_id, to_member_id, kind, body, member_id); `ws_call_join` takes `again` (Join here). A window's `member_id` other than the party's live row changes nothing.
- Guest page: `call_join`, `call_leave`, `call_signal`, `call_sync` (the guest's one-second read, `calls-guest.md`), with its csrf.
- API and MCP: `GET /workspace/channels/{id}/call` (`workspace:read`, `api_calls.php`), the live call or null, after the clean-up (#11765); nothing starts, joins or ends a call for a person.
- Off until the Calls card (CL6) turns calls on: a start or join is refused while off (`calls-settings.md`).
