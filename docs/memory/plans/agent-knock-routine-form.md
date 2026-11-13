---
name: agent-knock-routine-form
description: Plan (architect 2026-11-13, #9241): a push door on a routine's fire address is knocked in the routine's form (it answered HTTP 400 to the webhook form); one step, RK1, then a live check
---

## What is asked?
- Owner (2026-11-13), on waking a cloud session by the site's knock: "pr merge ile deneyelim". The trial (`workspace/agent-door-knock-trial.md`): the routine's fire address answered HTTP 400 to the knock.
- The architect's decision, a technical one (#9241): a door is knocked in the form its address takes.

## Why was the knock refused?
- `ws_agent_knock()` (`includes/workspace/agent_queue.php`) sends every push door the webhook form: `ws_agent_knock_body()` (`event`, `site`, `agent`, `request_id`) and `ws_agent_knock_headers()` (bearer token, `X-Pinegrap-Timestamp`, `X-Pinegrap-Signature`).
- A routine is fired by `ws_claude_fire()` (`includes/workspace/claude.php`) with `anthropic-beta: WS_CLAUDE_BETA`, `anthropic-version: 2023-06-01` and the body `{"text": …}`. The knock sends neither header and no `text`.
- `ws_agent_door_problem()` names "a routine's fire address" as a push door: the screen promises it.

## RK1 (S) The routine's form
- An address `ws_claude_url_valid()` accepts is knocked in the routine's form: the four headers `ws_claude_fire()` sends, the door's secret as the bearer token, no `X-Pinegrap-*` header; the body `{"text": "site: <API address> · agent: @<handle> · request: <id>"}`. Never the words of the message.
- Every other address: today's body and headers, byte for byte.
- A routine's refusal uses the sentences `ws_claude_fire()` already has: 401, 403 and 404 "claude.ai refused the routine's address or token (HTTP {var:1}). …", 429 "The routine's daily run allowance is used up.".
- Any other refusal, from any push door, keeps the answer's own reason beside the status: `error.message` of a JSON answer, else the start of the text; one line, at most 120 characters, escaped where the row is shown. One new sentence, added with `tools/tr_add.php`.
- The retry (`ws_agent_knocks_retry()`), the counters and the queue stay: a refused knock still leaves the request for a poll.
- Pure functions for the two forms and for the reason, tested in `tests/workspace_agent_queue_test.php`; the webhook form's tests stay green unchanged. No schema, no setting.
- Memory: `workspace/agent-doors.md` ("How is a push door knocked?") says both forms. It is near the size limit: shorten what the trial file already says.
- PR body: covered by the version's `[FIX]` line.

## What does a knock on a routine cost?
- Each knock is one run of the routine, counted against the account's daily run allowance. A door knocked often can use it up; the requests then wait for a poll, as after any refusal.
- A routine bound to a session ends with it: the door is entered again when the session changes (`workflow/agent-channel-experiment.md`).

## How is it checked after RK1?
- The worker cannot reach a token: its proof is the unit tests.
- The architect repeats the trial on dev: the door of `@prmerge` kept its address and token. Expected: a 2xx, `door_failures` 0, and the session saying the routine's turn woke it. The result goes into `workspace/agent-door-knock-trial.md` and this plan is deleted.

## Which steps are there?
- RK1 (S) The routine's form: done, #9273. Left: the live check above.
