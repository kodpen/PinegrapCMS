---
name: agent-discussion-follow
description: A discussion that follows a registry agent (A4b4 #8608): the agent:<id> / model:<id> key, the reaching row read live, the name that starts it, the followed ask through ws_agent_after_send()
---

## What did it fix?
- #8608, on the architect's decision (`workflow/decisions-made-workspace-12.md`). Before it, asking `@agent` in a discussion did not make it the followed one, and the next message that asked nobody did not ask it.

## Which key is kept?
- `ws_thread_follow_clean()` keeps `agent:<id>` and `model:<id>`, the value the agent's `ws_ai_requests.agent` carries (`ws_agent_request_value()`); `agent:0`, `agent:x` and `app:<n>` give ''. A model assistant is a registry agent woken by the same `ws_agent_after_send()`, so it is followed the same way; its answer comes from the site's loop (`models` in `ws_message_ask_assistants()`).

## When does it reach?
- Only while a row reaches the discussion, read live (`ws_agent_reach_rows()`, `ws_thread_follow_reach_pick()`, `ws_thread_follow_reaches()`): its own allowed row, or with none the unarchived channel's. Taken out of the channel, the channel archived, the own row `allowed` 0 or the agent paused: not followed (`ws_thread_follow_agent()` gives ''), not shown (`ws_thread_present()`), not asked, and Follow again does not bring it back (`ws_thread_follow_last()`).
- The handle is the row's `@handle` (`ws_thread_follow_handle($agent, $channel)`), also in the system lines of Stop / Follow again; no channel, no handle.

## What starts the follow?
- In the discussion only a name: `@handle` as a word or `<@app:N>` (`ws_thread_agents_named()`, the mention part of `ws_agent_wake_matches()`). The `all`, `prefix` and `replies` rules, the channel's or the discussion's own, never start it.
- `ws_thread_agents_asked($body, $message_id, $channel)` lists Claude, then the MCP agent or Pinegrap AI, then the named registry agents by id; the last is followed.
- At the start (#2419 answer 5, #NNNN): a discussion about a registry agent's or model assistant's answer, found by `ws_ai_requests.reply_message_id` with an `agent:`/`model:` value, or about a message naming one, follows it (`ws_thread_message_agent($message, $channel)`, `ws_thread_answer_agent()`), while a row reaches the discussion; else nobody. Follow again reads it the same way.

## How is it asked?
- `ws_thread_follow_ask()` sends a registry key to `ws_agent_after_send(..., $followed)`, never to `ws_ai_after_send()`. Followed mode reads only that agent's row and skips only the name check: own-message rule, depth and hourly brakes (with their system line) and the push knock stay. The pile-up rule (`ws_thread_follow_pending_plan()`) holds as for the others.
- A message that woke the followed agent already (its own row's rule) is not asked twice; the asked value goes into `agents`, and `models` is computed after.

## What is not done?
- `followLine` in workspace.js is unchanged: it draws the handle with the assistants' sign.
- Tests: `tests/workspace_thread_follow_test.php`. No database here: the paths with rows, a real agent's answer and the screen were not tried.
