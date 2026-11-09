---
name: model-assistant-channel-rule
description: Where a model assistant is asked (its own ws_channel_agents row; Pinegrap AI's ai_access switch does not count for it), how the shared loop's failure sentences name it, what stays closed (PR #8413)
---

## Where may a model assistant be asked?
- Where its own row in the channel allows it (`ws_channel_agents.allowed`), the measure an application agent's answer has (`ws_api_agent_request_place()`). `ws_model_channel_allowed($agent, $channel)` reads the row of the channel the request was made in and gives `ws_model_channel_verdict()` (pure).
- `ai_access` is Pinegrap AI's switch alone, as `claude_access` is Claude's: a channel closed to Pinegrap AI (2), or a private one nobody opened to it (0), does not keep out an assistant its people added (owner, 2026-11-10, `workflow/decisions-made-workspace-9.md`). Who wants it out takes it out of the channel.
- Not in a guest's room or a channel shared with a guest (`ws_channel_share_open()`), as no assistant is; a discussion follows its channel in that.
- Pinegrap AI's own rule is unchanged: `ws_ai_channel_allowed()` (`ai_access`, by kind when 0).

## Who asks that rule?
- The loop, through `ws_ai_loop_channel_allowed($channel)`: the running assistant's rule while `ws_model_running()` is set, Pinegrap AI's otherwise. Its places: `ws_ai_briefing()` (the request's start), `ws_ai_deliver()`, `ws_ai_finish_fail()` (whether the failure line is written in the channel) and `ws_ai_request_channel()` (a run on a task, which no agent gets: `delegate` false).
- A new check inside the loop asks `ws_ai_loop_channel_allowed()`, never `ws_ai_channel_allowed()`, or a model assistant is judged by Pinegrap AI's switch again (`tests/workspace_model_agents_test.php`).

## What did it fix? (PR #8413)
- An assistant added to a private channel nobody had opened to Pinegrap AI never answered: `ws_ai_briefing()` refused the request before the model was called ("Pinegrap AI may not be asked in this channel."), and `ws_ai_finish_fail()` wrote no line there, so only the request line under the message said so. Reproduced in a throwaway local install against a stand-in chat API (2026-11-10); after the change it answered there, and in a channel closed to Pinegrap AI.
- An assistant taken out of a channel while its request is open no longer answers there, in a public channel either.

## How do the loop's failures name the assistant?
- `ws_ai_loop_sentence($which)` (`unreachable`, `no_model`, `undone`, `unfinished`, `channel`): Pinegrap AI's wording for its own requests, `ws_model_loop_sentence($agent, $which)` with the assistant's name while one runs. Used by `ws_ai_step()` (the attempts limit, no model, a `fail` call without a reason), `ws_ai_final()` and the channel refusals of `ws_ai_briefing()` and `ws_ai_deliver()`.
- `ws_ai_http_error()` turns to `ws_model_http_error()` while an assistant runs: a call made inside its request (a draft through `pg_ai_chat()`) goes to the assistant's API (`ws_ai_http()`).
- In Turkish the name takes no case ending ("{var:1} ile bağlantı kurulamadı", not "{var:1}'a ulaşılamadı"): an ending does not fit every name.

## What still says Pinegrap AI for a model assistant?
- The Site Log line of its answer (`ws_ai_log()`: "Pinegrap AI answered a request in #… (Pinegrap AI)") and `pg_ai_chat()`'s own sentences (`includes/translate/engine_ai.php`).

Related: docs/memory/workspace/model-assistants.md, docs/memory/workspace/agent-registry.md, docs/memory/workspace/agent-doors.md
