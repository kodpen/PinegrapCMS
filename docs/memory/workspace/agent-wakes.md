---
name: agent-wakes
description: An app agent's scheduled wakes (every N minutes, once at a time) made by the general job, /wake in a channel, the agent-wake API routes, and the brief an app agent reads (A4b1, #8246)
---

## How is a schedule set?
- On the agent's row (`wake_every` minutes 1-1440, `wake_next_at`, `wake_note` up to 250): the Agents card; `/wake @agent every 30m note`, `/wake @agent at [day] 21:00 note`, `/wake @agent off`, `/wake @agent` (says it, only to the writer) in a channel, also `/uyandır` with `her 30dk`, `saat`, `kapat` (`ws_agent_wake_parse()`, `includes/workspace/agent_wake.php`); `GET|POST /workspace/agent-wake` for the agent itself (one of `every`, `at`, `off`; dry run).
- From a channel: whoever may write there (a member in a private one), the agent allowed there and active (`ws_agent_wake_problem()`); a system line names who set it. App agents only: a model agent is answered by the site and needs a message.
- A time already gone today means tomorrow; `at` with a day gone is refused.

## How is a wake made?
- `job.php` calls `ws_agent_wakes_run()` on every tick when `ws_agent_wakes_due()` finds one (one small read). Each due agent's schedule moves on first by a conditional UPDATE on the old `wake_next_at`, checked by affected rows, so overlapping ticks make one wake; `every` moves on from the due time, or from now after a long gap (one wake, not a backlog); `at` clears itself and its note.
- The request: `ws_ai_requests` with `message_id` 0, `agent` `agent:<id>`, `requested_by` the agent's `created_by`, the channel the agent's only allowed channel or 0 when it has several, `ai_state` `{"wake":{"kind","note","due"}}` (`ws_agent_wake_of()` reads it back). No schema: the row's state column carries it. The hourly brake holds; a push door is knocked as for a message.
- No new wake while an earlier one of the agent is queued or knocked and unclaimed (`ws_agent_wake_waiting_where()`); the schedule moves on. An app agent's request unclaimed for a day is cancelled (`ws_agent_queue_expire()`, from `ws_agent_knocks_retry()`); a message's line says the agent did not take it. Why: a one-minute schedule queued one at every due time (#8411).
- The request's API shape has `wake` {kind, note, due_at}; kind '' for a message's request.
- Answer: no mention and no parent; with channel 0 the answer route refuses (422 text): the agent writes with `workspace.messages.create` and closes with `no_answer`. A fail tells nobody.

## What is in the brief?
- `GET /workspace/agent-requests/{id}/brief` (`ws_agent_brief()` in `agent_queue.php`, shape `WorkspaceClaudeBrief`): the head (`ws_ai_briefing_head()` as an outside agent, its name and @handle), the place (channel or discussion, or the schedule and the agent's channels with ids), the request or the wake's note in a data block, the channel's memory index and decisions (`ws_ai_memory_parts()`), the loop-guard text (`ws_agent_loop_guard_lines()`) unless the channel row's `loop_guard` is 0, the site's `mcp.php` address and the key's scopes, the answer/fail routes, language and rules.
- The loop-guard text is guidance: the depth and hourly brakes stay in code (`workflow/decisions-made-workspace-7.md`).

## What did the live trial show? (dev, 2026-11-10)
- `/wake @mimar` said the schedule to the writer only. `/wake @mimar every 1m <note>` in channel 25 set the schedule and left a system line; the general job (a tick a minute) made a wake request within 30 s of each of two due times, shaped as above. `off` cleared the three columns; the next tick made nothing. `/uyandır @mimar saat 00:30 <note>` made one request (kind `at`) 49 s after 00:30 and cleared itself.
- A wake shows nowhere in the channel: no line and no mark until the agent writes.
- A wake's brief and `GET agent-wake` read with the agent's key: `agent-key-trial.md`; `POST agent-wake` and a text answer to a wake: `agent-key-trial-2.md`. Not tried: a knock from a wake, an agent in several channels, the card's schedule field. Tests: `tests/workspace_agent_wake_test.php`.
