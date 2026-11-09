---
name: workspace-ai-call-timeout
description: How long one Pinegrap AI model call may take (500 s, PG_AI_CALL_TIMEOUT 20-600), why it is no longer 55 s, what still cuts a call off, the levers to measure before changing limits
---

## How long may a call take?
- `ws_ai_call_timeout_for($configured)` (pure, includes/workspace/ai.php): 500 seconds, or `PG_AI_CALL_TIMEOUT` from data/config.php when it is 20 or more, at most 600. `WS_AI_CALL_TIMEOUT` is read from it; `ws_ai_call_timeout()` gives the command line (the hourly `ws_ai_job()`) at least `WS_AI_CALL_TIMEOUT_JOB` (240).
- Every caller reads it: the channel requests (`ws_ai_kick()`, `set_time_limit()` + 60), the connection test, recaps, bulk and mail triage, AI writing, the translation engine (its fallback is 500 too).

## Why not 55 seconds?
- 55 s came from the Ollama server Pinegrap AI first ran on (owner, 2026-11-03); the engine is another one now. On dev (2026-11-03) a ten-box flow with derived values was cut off three times and given up ("Pinegrap AI could not be reached or took too long"); the model server's own trace of a simple flow showed 284 s of reasoning before the answer.

## What can still cut a call off?
- The web server: IIS FastCGI `activityTimeout` / `requestTimeout`, nginx `fastcgi_read_timeout`, PHP-FPM `request_terminate_timeout`. A browser or proxy that stops waiting (Cloudflare answers 524 after 100 s) does not: `ws_ai_kick()` sets `ignore_user_abort(true)`; the next kick reads `busy` until the call ends and the lock goes.
- A request with no step for `WS_AI_STALE` (900 s) is given up by the watchdog.

## What does a model server error do?
- A tool call the server could not read (HTTP 400 "does not match the expected peg-native format") is `format` in `ws_ai_response_problem()`: the request is answered in words (`ws_ai_final()`), no draft card. Other errors hold requests 15 or 60 s (`ws_ai_hold()`), three failed tries give the request up.

## What could make Pinegrap AI slow, and what is measured first?
- Owner (2026-10-24, after long tests ran inefficiently): measure first, change limits after. Not the key.
- The levers: nobody picks the model (`ws_ai_model()` keeps the first one GET /models lists); `WS_AI_MAX_STEPS` 8; past `WS_AI_BUDGET` 20 s the next call waits for an open screen or the hourly job; `max_tokens` 3000; 20-30 channel messages of context (`ws_ai_channel_context()`, fewer on a retry); the size of `ws_ai_tools()` and `ws_ai_system_prompt()`.
- What to measure in `ws_ai_requests` (agent `ai`): `ai_steps`, `ai_attempts`, `claimed_at` to `answered_at`, `error`, the size of `ai_state`; tokens are not kept.

## Tests
- tests/workspace_ai_call_timeout_test.php.
