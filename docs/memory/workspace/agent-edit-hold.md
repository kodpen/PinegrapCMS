---
name: agent-edit-hold
description: An agent waits while its message is edited: the edit box's beat, held_until, "Being edited, @agent is waiting", 409 editing / edited, a save in each status (#8869)
---

## When is a request held?
- While the writer has the edit box of their own message open and the message has an open request (queued, sent or running), of any agent kind, in any channel kind (owner, 2026-11-11, #8723: `workflow/decisions-made-workspace-14.md`).
- The browser calls the panel action `ws_edit_hold` (`message_id`) when the box opens and at most every 20 seconds while they type (`editHoldStart()`/`editHoldBeat()` in `workspace.js`). A message whose line shows no open request makes no call.
- Each beat sets `ws_ai_requests.held_until` to now + `WS_EDIT_HOLD_SECONDS` (120) for the message's open requests (`ws_edit_hold_beat()`); only the writer, a person who may still read the channel, holds it. The column: `workflow/decisions-made-schema-16.md`, step 11.0.192.

## What does a held request meet?
- It is handed to nobody: left out of `GET /workspace/agent-requests` and Claude's request list, its claim answers `409 editing` (not `held`: Claude's claim already says held for the other side's request), no push door is knocked (`ws_agent_knock()` returns early), and Pinegrap AI, the model agents, the MCP agents and Claude's routine skip it (`ws_edit_hold_free_sql()` in every picker).
- An answer while held: `409 editing`, the request stays as it was.
- The line under the message, for every agent kind: "Being edited, @agent is waiting" with a pencil (`ws_edit_hold_state()` over `ws_claude_request_state()`).

## How does the hold end?
- Cancel (the box's ×, Esc, another channel, a reply picked): `ws_edit_hold` with `release`, the words of before. Two minutes with no beat: `ws_edit_hold_expire()` (from `ws_agent_knocks_retry()`, so screens waiting and the hourly job) ends it the same way.
- Save (`ws_message_edit()`): `ws_edit_hold_release(…, true)`. Each end sets `held_until` 0, touches the message and knocks the push doors of the queued app-agent requests.

## What does a save do in each status?
- queued / sent: nothing more; the claim or the brief reads the new words.
- running, Pinegrap AI or a model agent (run by the site): queued again with `ai_state` cleared, so the run starts over on the new words.
- running, an application agent or Claude: its next answer gets `409 edited` when `ws_messages.edited_at` is after its `claimed_at`; the request goes back to queued (eye taken off, Claude's `claimed_device_id` cleared) and is claimed again. A dry run is refused the same way and changes nothing.
- answered, failed, cancelled: never asked again.
- Not covered: an MCP agent's task already sent to its server runs on with the words of before.

## Where are the tests?
- `tests/workspace_agent_edit_hold_test.php`: when a request is held, the answer after a save in each status, the codes, the line, that every picker, door and route honours the hold, the action, the step and the browser's calls.

Related: workspace/agent-doors.md, workspace/agent-shown.md
