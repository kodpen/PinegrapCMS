---
name: api-idempotency-key
description: External API Idempotency-Key: reservation row before the write, 409 idempotency_in_progress on an overlapping retry, errors give the key back, 300 s stale take-over
---

## What does an Idempotency-Key do on a write?
- `includes/api/idempotency.php`, called from `includes/api/bootstrap.php` for every POST/PUT/PATCH/DELETE that is not a dry run. The key is stored per application (`api_idempotency`, unique on `app_id, idem_key`); a device session's key is hashed with the device id (`api_idempotency_scoped_key()`).
- The fingerprint is the method, the path and the parsed input (`api_idempotency_hash()`), with a form file part's name, size and SHA-256 (`api_idempotency_input()`). Input JSON cannot write (bytes that are not UTF-8) is taken as `serialize()` text (`api_idempotency_input_text()`, `api-response-json-encoding.md`).

## Why is a row written before the write runs?
- Before PR #946 (issue #817) the record was written only after the write had answered, so a client that timed out and retried while the first request was still running found nothing and applied the write again (six overlapping bulk stock calls under one key: six decrements).
- Now `api_idempotency_replay()` inserts a reservation (`status_code = 0`, `response` NULL) with `INSERT IGNORE`; the unique key lets exactly one attempt in. A successful answer is written onto that row (`api_idempotency_store()`), an answer of 400 or more deletes it (`api_idempotency_forget()`), and a request that ends without `api_send()` (fatal error) deletes it at shutdown (`api_idempotency_release()`).

## What does an overlapping retry get?
- `api_idempotency_decision($row, $hash, $now)` (pure, `tests/api_idempotency_test.php`): no row → run; same fingerprint, finished → replay with `Idempotent-Replay: true`; other fingerprint → 409 `idempotency_key_reused`; same fingerprint, still `status_code = 0` → 409 `idempotency_in_progress` with `Retry-After: 1`.
- A reservation older than `api_idempotency_pending_ttl()` (300 s) is stale: the next attempt takes it over with a conditional UPDATE and runs.
- `status_code = 0` is never a recorded answer; nothing in the schema changed (the column has always been `SMALLINT UNSIGNED DEFAULT 0`).

## What stays as it was?
- A dry run takes no key. A route run by the MCP endpoint answers through its hook and records nothing; its reservation is given back at shutdown. Rows are purged after a day (`api_maintenance_purge()`).
