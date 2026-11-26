---
name: bug-hunt-areas-agent-requests
description: The bug hunt in the agents' request queue and wakes (architect, 2026-11-26, request 3016): what was fixed, what was read clean, what is asked and left
---

## Which area was it?
- The agents' request queue and wakes: `includes/workspace/api_agent_requests.php` (list, claim, answer, fail, progress, brief, agent-wake routes), `includes/workspace/agent_wake.php` (schedules and wake requests), and the queue parts of `agent_queue.php` they call (brakes, knock, retry, expiry).
- To look at: another agent's key, two claims of one request, an answer, none or fail on a closed request, a request left running, a wake made twice or for a paused agent, the brakes, the knock's retry.

## What was fixed?
- Answer: the request is closed by a conditional update before the reply is written; a second answer (a retried call, a second run) or one racing a person's Cancel, the day's expiry or a clear gets 409 `closed` and writes nothing. A reply that cannot be written puts the request back as it was read (`ws_agent_request_reopen_sql()`). Before, both answers wrote a reply and both got 200.
- Fail: closed before the reason is written under the message; a second fail or one racing an answer writes no second line, 409 `closed`.
- Wakes: a run on a task handed to the agent (no message, `task_id` set) counted as a waiting wake in `ws_agent_wake_waiting_where()`, so every due wake moved the schedule on and made nothing while the task waited. Now `task_id = 0` too.
- Expiry: `ws_agent_queue_expire()` cancelled in one bulk update and stamped no message, so open screens kept the line "asked" until a reload. Each cancelled request now stamps its message (`ws_message_touch()`), as Claude's and Pinegrap AI's expiry do.
- Tests: `tests/workspace_agent_queue_test.php`, `tests/workspace_agent_wake_test.php`.

## What is asked, not built?
- A claimed (`running`) app agent request whose agent stopped stays running for ever: the eye and "is working on it" stay, and a flow's agent box waits for its own look. Claude and Pinegrap AI fail theirs after 15 minutes (`WS_CLAUDE_STALE`, `WS_AI_STALE`), but an app agent may legitimately work for hours (the progress note's "back by 22:30"), so the limit is a product choice for the architect.
- An agent removed from every channel keeps its schedule: each wake is made with channel 0 and its brief names no channel. Whether removing the last channel row clears the schedule is a product choice.

## What was read and found clean?
- Every route reads the request through `ws_api_agent_request_or_404()` with the caller's own `agent:<id>`: another agent's key gets 404 on claim, answer, fail, progress and brief, and the list shows only its own.
- Two claims: the conditional update on `queued`/`sent` with affected rows; the loser gets 409 `already_claimed`.
- A paused agent: its key gets 403 with the paused sentence; no wake is made (`status = 'active'` in `ws_agent_wakes_due()` and `ws_agent_wakes_run()`), no message wakes it (`ws_agent_channel_rows()`), no retry knock.
- One wake per due time under overlapping ticks: the conditional update on the old `wake_next_at`; a person's `/wake off` between read and update wins.
- The brakes: the depth count of the reply chain and the hourly count of the agent's requests come before every insert, a wake's too.
- The knock's retry: the newest queued request not edit-held, after `ws_model_hold_seconds()` from the last knock.

## What is left?
- A task run cancelled by the day's expiry does not tell the person who asked, as with Claude's and Pinegrap AI's queue expiry.
