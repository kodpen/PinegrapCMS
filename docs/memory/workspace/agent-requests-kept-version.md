---
name: agent-requests-kept-version
description: No agent request, claim, answer, emoji or eye on a message of an earlier version of a cleared conversation; the clear cancels the open requests it freezes (#9593, bug #9588)
---

## What happens to agent requests when a conversation is cleared?
- `ws_channel_clear()` cancels the channel's open requests (queued, sent, running; Claude, Pinegrap AI, model, application and MCP agents) whose message goes under the new floor: `ws_agent_requests_freeze()` in `eras.php`, error "The conversation was cleared, so the request was cancelled." No eye is taken off, the kept message is not touched, a flow's agent box waiting on it settles.

## Can a request on a kept message be asked again?
- No. The Agents card offers no **Ask again** when the failed request's message is deleted or in an earlier version (`ws_agent_activity_actions()`'s `$message_open`, read by `ws_agent_request_message_check()`), and `ws_agent_activity_retry()` refuses with `ws_message_parent_refusal()`'s sentence. Claude, Pinegrap AI and MCP agents have no Ask again of their own.

## What does an application agent meet on such a request?
- Claim and answer (`ws_api_agent_request_kept_stop()`): the request is cancelled with the clear's reason (not on a dry run) and 409 `conversation_cleared`, before any write. Fail is recorded, but no reply is written under the message and no mark is taken off.

## Which marks stay off a kept message?
- `ws_agent_react()`, `ws_ai_react()` and `ws_claude_react()` do nothing there (`ws_message_kept()`). `ws_agent_emoji_problem()` refuses the emoji (`$in_past`). `ws_reaction_check()` refuses for every caller, the API's reaction route included; the panel's `ws_react` had its own guard before.

## What did it fix?
- After the clear of 2026-11-15 on dev, Ask again on a failed request of message 702 (Version 1) made requests 376 and 378, and the answer left a reaction and a state line on the kept message. Tests: `tests/workspace_agent_kept_version_test.php`. The failed line reading "Claude could not do it" under a message is bug #9594.
