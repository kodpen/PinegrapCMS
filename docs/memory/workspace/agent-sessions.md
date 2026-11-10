---
name: agent-sessions
description: The asker's own Claude session over OAuth: @Claude waits 2 min for the asker's online connection, then the routine; claimed_device_id; via_client_id in the depth brake
---

## Who takes a request to Claude? (A8a1, #8489)
- `ws_claude_request_holder()` (`includes/workspace/claude.php`, pure): a request the asker's connection claimed (`ws_ai_requests.claimed_device_id` > 0) stays with it; an open request asked in a message waits for the asker's own session while one is online, for `WS_CLAUDE_SESSION_WAIT` (120 s); after that, or with none online, it is the routine's as before.
- Online: an `api_devices` row of the asker with `oauth_client_id > 0` and `last_used_timestamp` under 120 s old (the column is written at most once a minute, so a session polls more often than that). Any OAuth connection counts.
- Always the routine's: a request whose asking message was written through a connection (`ws_messages.via_client_id` > 0; the session would be asking itself), the daily summary, a note, a task run, the Visual Page Editor.
- Why: owner decision #8490 (2a), recorded in `workflow/decisions-made-workspace-11.md`.

## Which routes serve the session?
- `ws_api_claude_session_routes()` in `api_claude.php`: list, brief, claim, answer, fail, change-types, record, through an OAuth connection (`ws_api_claude_session_device()`), still `'mcp' => false` (REST only). The instructions route stays the routine's.
- `ws_api_claude_request_for_caller()`: another person's request is 404 to a connection; one held by the other side is 409 `held`. The list shows each side only what `ws_claude_caller_may()` lets it take.
- A connection's claim skips the instructions check and writes `claimed_device_id`; the routine's claim and the connection's both need `claimed_device_id = 0`.
- Answer and fail write with `ws_api_writer_app_id()`: the routine as Claude's application, a connection as its connection writes (today the connection's application; A8a2 makes it the person with `via`). The 👀/✅ stay Claude's.

## When does the routine take a held request?
- `ws_claude_dispatch()` counts and `ws_claude_fire()` marks sent only `ws_claude_routine_ids()`: a held request starts no run and stays queued, so the next `ws_tick` of a screen or the hourly job sends it once its wait is over.

## What does the depth brake read?
- `ws_agent_chain_entry()` (`agent_queue.php`, pure): a message with `via_client_id` > 0 is an agent's message, so two sessions answering each other stop as two agents do (`agent-doors.md`).

## What is left for A8a2?
- Writing a connection's message as the person (`sender_kind = 'user'`, `via_client_id`), the API's `via`, the "via <client>" badge, and the thread-follow decision treating such a message as an application's (`plans/agent-channel-steps-2.md`).
- Schema step 11.0.184 `upgrade_2026_11_0_ws_agent_sessions()` brought both columns; not run on a dev DB in #8489.
