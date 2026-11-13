---
name: agent-channel-post-repeat
description: tools/ws_post.php never writes a channel line twice: every post has an Idempotency-Key, a call without an answer is repeated once with it, exit 3 names the key the caller repeats with
---

## What does the tool do with a call that gets no answer?
- `tools/ws_post.php` sends every post with an `Idempotency-Key`: the `--idempotency-key` given, or `ws-post-<24 hex>` made for the run (`ws_post_own_key()`).
- A call without an answer (network error, timeout, a gateway's 502 or 504; `ws_post_no_answer()`) is repeated once with the same key. A 409 `idempotency_in_progress` (the first call still running) waits its `Retry-After` (1 to 10 s) and asks once more (`ws_post_send()`, `tests/ws_channel_tools_test.php`).

## How does a caller repeat after exit 3?
- Exit 3: still no answer, so the line may or may not be written. stderr ends with `repeat with --idempotency-key=<key>`; the caller repeats with that key, never without: the site then replays the first answer or writes the line once.
- Exits 0 (written), 1 (refused) and 2 (usage, settings) keep their meaning.

## Why?
- `ws_client_request()` gives up after 30 s; on 2026-11-13 dev answered in about 30 s for forty minutes. A call that timed out could be written, and a repeat without a key wrote it twice (`plans/channel-post-tool.md` CP1, PR #PRNUM). The key rule of the site: `api/api-idempotency-key.md`.
