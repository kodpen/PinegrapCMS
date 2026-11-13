---
name: channel-post-tool
description: Plan (2026-11-13): tools/ws_post.php never writes a channel message twice after a call without an answer, and posts a picture file, so visuals go straight into the agent channel
---

## What is asked?
- Owner (2026-11-13), on the architect's review of the agent channel: the send tool must not post the same message again after a call that got no answer. `ws_client_request()` gives up after 30 s; on 2026-11-13 dev answered in about 30 s for forty minutes. A call that timed out may have been written, and repeated without a key it is written twice. The tool has `--idempotency-key` (`api/api-idempotency-key.md`), but only a caller that thinks of it sends one.
- Owner (2026-11-13): "tasarlanan görseller kanala direkt konsun. ben oradan kontrol edeyim." The tool cannot send a file; the API can (`POST /workspace/channels/{id}/files`, form part `file`, `api/api-file-endpoints.md`).
- The working rules this serves: `workflow/owner-contact-channel.md`.

## Which steps?
- CP1 (S) No second message after a call without an answer: without `--idempotency-key` the tool makes a key of its own for the run, repeats the call once with the same key, and when that gets no answer either exits 3 with the key on stderr. The caller repeats with `--idempotency-key=<key>`, never without.
  - A 409 `idempotency_in_progress` waits and asks once more. A unit test covers the key, the repeat and the exit; `workflow/agent-channel-wake.md` says how a caller repeats.
- CP2 (S) `--file=PATH` posts one file with the text as its message through the files route (multipart, the key rule of CP1, `--parent` kept) and prints the message id; a refusal (size, type) comes back as the API's line. One file a call: a set of screenshots is one message and its answers.
  - After it lands `workflow/owner-contact-channel.md` drops the raw-link way and this plan is deleted.

## What does every step keep?
- Nothing secret is printed; exits 0, 1 and 2 keep their meaning; PHP 7.1. The tool lives in `tools/`, so no translation and no changelog line.
- Both steps change `tools/ws_post.php`: CP2 after CP1. Claim as `[plan:channel-post-tool] <step> — <topic>`.
