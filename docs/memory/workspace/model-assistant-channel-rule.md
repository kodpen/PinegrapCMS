---
name: model-assistant-channel-rule
description: Where a model assistant is asked (its own ws_channel_agents row; a channel closed to Pinegrap AI on purpose keeps it out too) and how the shared loop's failure sentences name it (PR #8413)
---

## Where may a model assistant be asked?
- Where its own row in the channel allows it (`ws_channel_agents.allowed`), the measure an application agent's answer has (`ws_api_agent_request_place()`). `ws_model_channel_allowed($agent, $channel)` reads the row of the channel the request was made in and gives `ws_model_channel_verdict()` (pure).
- With no choice made (`ai_access` 0) the row alone decides, in a private channel too: Pinegrap AI's default there (no) does not keep out an assistant the channel's people added.
- Not in a channel closed to Pinegrap AI on purpose (`ai_access` 2), whatever the row says (owner, 2026-11-10, `workflow/decisions-made-workspace-9.md`): closing takes the channel's manager (`ws_ai_channel_set()`), while a row can be a member's (in a public channel, or older than `channel-agent-rights.md`), so an answer there would let a member send a closed channel's words to the provider.
- The menu's switch is drawn only where Pinegrap AI is set up (`ws_ai_channel_state()`) and shows a private channel unchecked for 0 and 2 alike; on a site without Pinegrap AI no channel is closed this way.
- Not in a guest's room or a channel shared with a guest (`ws_channel_share_open()`), as no assistant is; a discussion follows its channel in all of this.
- Pinegrap AI's own rule is unchanged: `ws_ai_channel_allowed()` (`ai_access`, by kind when 0).

## Who asks that rule?
- The loop, through `ws_ai_loop_channel_allowed($channel)`: the running assistant's rule while `ws_model_running()` is set, Pinegrap AI's otherwise. Its places: `ws_ai_briefing()` (the request's start), `ws_ai_deliver()`, `ws_ai_finish_fail()` (whether the failure line is written in the channel) and `ws_ai_request_channel()` (a run on a task, which no agent gets: `delegate` false).
- A new check inside the loop asks `ws_ai_loop_channel_allowed()`, never `ws_ai_channel_allowed()`, or a model assistant is judged by Pinegrap AI's switch again (`tests/workspace_model_agents_test.php`).

## What did it fix? (PR #8413)
- An assistant added to a private channel nobody had opened to Pinegrap AI never answered: `ws_ai_briefing()` refused the request before the model was called ("Pinegrap AI may not be asked in this channel."), and `ws_ai_finish_fail()` wrote no line there, so only the request line under the message said so. Reproduced in a throwaway local install against a stand-in chat API (2026-11-10); after the change it answered there, and in a channel closed to Pinegrap AI its request failed under its own name, its API not called.
- An assistant taken out of a channel while its request is open no longer answers there, in a public channel either.

## How do the loop's failures name the assistant?
- `ws_ai_loop_sentence($which)` (`unreachable`, `no_model`, `undone`, `unfinished`, `channel`): Pinegrap AI's wording for its own requests, `ws_model_loop_sentence($agent, $which)` with the assistant's name while one runs. Used by `ws_ai_step()` (the attempts limit, no model, a `fail` call without a reason), `ws_ai_final()` and the channel refusals of `ws_ai_briefing()` and `ws_ai_deliver()`.
- `ws_ai_http_error()` turns to `ws_model_http_error()` while an assistant runs: a call made inside its request (a draft through `pg_ai_chat()`) goes to the assistant's API (`ws_ai_http()`).
- In Turkish the name takes no case ending ("{var:1} ile bağlantı kurulamadı", not "{var:1}'a ulaşılamadı"): an ending does not fit every name.

## What still says Pinegrap AI for a model assistant?
- `pg_ai_chat()`'s own sentences (`includes/translate/engine_ai.php`). The Site Log line of its answer names it: `model-assistant-site-log.md`.

Related: docs/memory/workspace/model-assistants.md, docs/memory/workspace/agent-registry.md, docs/memory/workspace/agent-doors.md
