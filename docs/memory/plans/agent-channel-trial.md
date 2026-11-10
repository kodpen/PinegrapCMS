---
name: agent-channel-trial
description: Live trial of the agent channel on dev (2026-11-10): what is still to try and what the trial found wrong, fixed or not
---

## What was done, what is left?
- Done on dev.pinegrap.com: application 13 "ADNAN Mimar" is registry agent 2, `@mimar`, poll door, in channel 25 (mention, tier 1). Through the panel a mention and `/wake` (`workspace/agent-doors.md`, `agent-wakes.md`); with the application's key the agent's own side (`workspace/agent-key-trial.md`): requests 37-41 were listed, briefed, claimed, answered or failed, and none waits.
- Left to try: a push door's knock against a real receiver, an agent in several channels, the Agents card's schedule field, a text answer to a wake, `POST /workspace/agent-wake`, the agent's edits of its own messages. The key (`PG_WS_SITE`, `PG_WS_KEY`, `PG_WS_SECRET`, Basic auth) is in the architect's session, not on the owner's machine.

## What did the trial find wrong?
1. Done (#8442; decided #8410): `ws_ai_requests.agent` `app:<n>` is the registry agent's id for an agent's request (`agent_queue.php`) and the application's id for a proposal (`proposals.php`), so agent n's list, brief and hourly brake take application n's proposals, and the proposals' readers take agent n's requests. An `app` agent's request moves to `agent:<ws_agents.id>` (`ws_agent_request_value()`, `ws_agent_request_parse()`, the `strpos` in `ws_agent_wake_of()`); `model:<id>` and the proposals' `app:<n>` stay. No schema (it fits `VARCHAR(16)`), no data step; a test with an agent and an application of one id.
2. Confirmed live, not fixed: a dry run of `POST /workspace/channels/25/changes` with the agent's key got 403. An active agent's application is in `ws_assistants()`, so `ws_api_proposal_agent()` refuses before `ws_agent_apply_within_tier()` can run: tiers 2 and 3 cannot be reached, and a registered application no longer proposes at all. The refusal points to the agent's answer, which takes only `text` or `no_answer`, while the brief tells it to propose. The fix lets an active `app` agent's own key through on the channel's changes route, the one `workspace/agent-risk-tiers.md` names; handing a task over stays refused.
3. Done (#8442; owner, #8411, `workflow/decisions-made-workspace-10.md`): no new wake while an earlier one of the agent still waits, and a queued request not claimed within a day is cancelled. Seen live: a one-minute schedule made a request at every due time.
4. Seen live: the @ list of a channel already open did not offer the agent just added in its settings drawer; after the page was loaded again it did (`brief['apps']` is read with the channel).
5. Seen live, Turkish keys shared with other screens: `Poll` reads "Oylama" on the Agents card's row (the door's select says "Yoklama"), `Prefix` "Başına" in the drawer, `Agents` "Danışmanlar" as the card's heading, `Handle` "Kullanıcı adı" though a handle is never a user name.
6. In the code: the drawer's Add list offers Claude, but no reader of `ws_channel_agents` takes a `claude` row for waking: its rule and tier there decide nothing.
7. Seen live, not the agents': every load of Workspace settings logs "Undefined array key archived_at" (`access.php` 352, called from `ws_github_link_channels()`).

## Done when?
- Each finding is fixed or dropped and the tries left are made; then this file is deleted.
