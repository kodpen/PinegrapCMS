---
name: object-storage-health
description: Object store health: failed calls counted for the ops alert storage_unreachable, the Storage card's last test result, the System Status tile with the move job's failures
module: object_storage_health
---

## What counts as a failed call?
- `pg_os_request()` hands every answer to `pg_os_health_unreachable()`: a cURL error other than 23 (sink or cap stopped it, a local matter), a 5xx, or 401/403 (credentials refused). A 404, a 400 or a call never sent (`pg_os_fail()` before cURL) is not counted.
- Noted in `data/temp/object_storage_failures.json` (time, message; last hour, newest 50, `pg_os_health_window()`) under `flock()`, written only when a call fails. The card's test counts too.

## When is storage_unreachable sent?
- `pg_ops_alerts_check_storage()` (source `storage`, after `webhooks`): while the store is set up and switched on (`pg_os_ready()`), 5 failed calls in the last hour (`pg_os_health_threshold()`; no setting, no column). Detail: the count and the newest message. Once a day per the ops-alert rule (`ops-alerts.md`); links to `#pgset-storage`.

## What does the Storage card show?
- Under "Save and test the connection": the last result, kept in config_text `object_storage_last_test` (`at`, `ok`, `message`) by `pg_os_health_test_save()`, read by `pg_os_health_test_last()`.

## What does System Status show?
- Tile "Object Storage" (short label "Storage", an actions-column line, not scored) only while the store is ready: red with the newest message when the hour holds 5 failures; else amber when the move job gave up on files (`pg_os_health_move_failures()` over `object_storage_move`, the first 5 names as detail); else green, "Reachable" or "{n} being retried".
- Erasure and export are unchanged: bytes are deleted where they are.

Tests: `tests/object_storage_health_test.php`.
