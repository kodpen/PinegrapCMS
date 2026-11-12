---
name: agent-channel-request-wait
description: tools/ws_wait.php --requests, a session's waiter on its own request queue (GET /workspace/agent-requests): which request wakes it, the printed line, the interval, exits, restarting
---

## What does it do? (PR #8767)
- `php tools/ws_wait.php --requests --timeout=7000` reads `GET /workspace/agent-requests` with the application's key every `--interval` seconds (15 by default, at least 5: the queue has no ETag and is read whole) and exits 0 on the oldest request whose status is `queued`.
- A `sent` or `running` request is already handed out and wakes nobody. The route lists `queued` and `sent` (`workspace/agent-doors.md`); the tool keeps the queued ones itself.
- Why: an answer to the agent's message and a scheduled wake make a request without the `@handle`, so a waiter on the text misses them ([[agent-channel-wake]]).

## What does it print?
- One JSON line: `request_id`, `message_id` (0 for a wake no message asked), `channel_id`, `channel` (name), `wake` (true for a scheduled wake), `wake_kind`, `requested_by` {`user_id`, `name`}, `text`.
- `text` is the message's plain text (`message.text`, tags as names; never `body` with `<@app:N>`), or the wake's note when there are no words; at most 500 characters, never cut inside a character.
- The key and the secret are only in the Authorization header, never printed.

## When does it exit?
- 0 on a queued request; 3 on timeout; 1 when the site refuses (401, 403, 404, 400) or answers no JSON; 2 for usage or missing settings. Other failures (network, 5xx) are waited out.
- It needs no channel: `PG_WS_CHANNEL` is not asked for (`ws_client_settings(…, false)`); the text mode, `ws_post.php` and `ws_poll.php` still need it.
- A text rule, `--changed` or `--after-id` with `--requests` is a usage error: one waiter waits on one thing, and a session that needs both runs two.

## How is it restarted?
- With Bash `run_in_background` (timeout 7200000), as the text waiter. A request stays queued until it is claimed, so a waiter started again before the claim wakes at once on the same request: claim it (or leave it on purpose) before restarting. There is no "after this one" option; the plan did not ask for one.

Tests: `tests/ws_channel_tools_test.php` (which row wakes, the line and its 500 characters, the interval's floor, the channel not needed).
