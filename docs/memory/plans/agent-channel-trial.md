---
name: agent-channel-trial
description: Live trial of the agent channel on dev (2026-11-10): what is left to try with the application's key, and what the trial found wrong, none of it fixed
---

## What was done, what is left?
- Done on dev.pinegrap.com through the panel: application 13 "ADNAN Mimar" is registry agent 2, `@mimar`, poll door, in channel 25 (mention, tier 1); a mention and `/wake` were tried (`workspace/agent-doors.md`, `agent-wakes.md`).
- Left, with application 13's key (`PG_WS_SITE`, `PG_WS_KEY`, `PG_WS_SECRET`, Basic auth; the architect's session holds them, the owner's machine does not): `GET /workspace/agent-requests` (37 and 41 from mentions, 38-40 wakes, all queued), `/37/brief` and a wake's `/38/brief`, `POST /37/claim` (the eye on message 696, the line turns to working), `POST /37/answer` with a text (the answer under the message, the tick), the others closed with `no_answer`, `GET /workspace/agent-wake`. The results replace the "not tried" lines of the two files.

## What did the trial find wrong? (not fixed, not decided)
1. In the code: `ws_ai_requests.agent` `app:<n>` is the registry agent's id for an agent's request (`agent_queue.php`) and the application's id for a proposal (`proposals.php`). `ws_proposal_where()` and `ws_proposal_agent_parse()` read every `app:` row as a proposal; agent n's list, brief and hourly brake take application n's proposals, and the proposals' hourly count takes agent n's requests. On dev agent 2 meets application 2 (Pinegrap AI), which proposes nothing.
2. In the code; one dry run of `POST /workspace/channels/25/changes` with the key settles it: an active agent's application is in `ws_assistants()`, so `ws_api_proposal_agent()` answers 403 before `ws_agent_apply_within_tier()` can run, and tiers 2 and 3 (`workspace/agent-risk-tiers.md`) look unreachable. By the same reading the route is closed to application 13 since it was registered.
3. Seen live: a wake is made while earlier ones still wait, and nothing cancels a queued `app:` request, so an agent that does not read gathers up to 30 an hour (`WS_AGENT_HOURLY_MAX`). AU1's Cancel (`agent-activity-usage.md`) is by hand.
4. Seen live: the @ list of a channel already open did not offer the agent just added in its settings drawer; after the page was loaded again it did (`brief['apps']` is read with the channel).
5. Seen live, Turkish keys shared with other screens: `Poll` reads "Oylama" on the Agents card's row (the door's select says "Yoklama"), `Prefix` "Başına" in the drawer, `Agents` "Danışmanlar" as the card's heading, `Handle` "Kullanıcı adı" though a handle is never a user name.
6. In the code: the drawer's Add list offers Claude, but no reader of `ws_channel_agents` takes a `claude` row for waking: its rule and tier there decide nothing.
7. Seen live, not the agents': every load of Workspace settings logs "Undefined array key archived_at" (`access.php` 352, called from `ws_github_link_channels()`).

## Done when?
- The agent's side is tried and each finding is fixed, decided or dropped; then this file is deleted.
