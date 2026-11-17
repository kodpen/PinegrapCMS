---
name: release-bundle-publish-errors
description: A database error, fatal error or timeout while a release bundle publishes: what is taken back, what is left, why
module: page_bundles
---

## What does a failed query during a publish do?
- `pg_page_bundle_publish()` runs its writes inside `pg_page_bundle_with_throwing_errors()`, so `output_error()` throws (`pg_error_throws()`) instead of ending the request with the run half done.
- `pg_page_bundle_write_item()` catches the throw after its claim and gives the item back to `waiting`; `pg_page_bundle_publish_ops()` turns a throw from a write into a failed write and from a take-back into one not taken back.
- So a failed query is a failure like any other: what this run wrote is taken back, the bundle keeps its status with `failed_page_id` and `failed_reason` (the error, "Query failed."), or goes `partial` only when a take-back failed too (#10047).

## Why was this needed?
- The page save (`pg_designer_save_page()`) runs its queries through `db()`, and a failed one called `output_error()`, which ended the request. The first page stayed live with the bundle's content, the next item stayed claimed `written` with no write, and Publish, Take back and removing the item all refused; only Delete worked (#10047).

## What happens to a publish that died (fatal error, timeout, lost connection)?
- It cannot be caught, so it is finished later (#10158). `pg_page_bundle_publish()` holds the bundle's named lock (`pg_page_bundle_lock()`, `GET_LOCK(…, 0)`) for the whole run; MySQL frees it when the connection ends. A free lock on a bundle with written items means its publish died; `written_at` is never used to guess by time.
- Who finishes it: `pg_page_bundle_with_lock()` at the start of Publish, Take back, Remove (`pg_page_bundle_remove_item($bundle, $id, $user)`) and Delete (`pg_page_bundle_delete($bundle, $user)`), and the general job (`pg_page_bundle_job_finish_dead()` in `pg_page_bundle_job_run()`), each only when it gets the lock without waiting. A held lock answers "The bundle is being published right now." and touches nothing. A plain read of the screen writes nothing.
- What it does (`pg_page_bundle_finish_dead()`, pure `pg_page_bundle_dead_plan()` and `_dead_run()`): in an open or scheduled bundle every written item, in a partial one only a claim with no `written_state`. A claim whose page is as it was (live state equals `live_state`; a new page still in Drafts) is given back to `waiting`; the written pages are taken back newest first by the failure's own undo; the bundle keeps its status with `failed_page_id` (the claim's page) and a reason.
- A take-back that fails, or a claim whose page is neither as it was nor recorded, moves it to `partial`; the take-back lists such a replaced page as `unfinished` ("A publish stopped while writing this page…").
- A scheduled bundle whose time the job had cleared goes back to `open` (time and `scheduled_by` cleared) and gets the `bundle_held` bell, as a timed publish that failed. The job takes pages back as who set the time, else who made the bundle, while role 0 or 1; otherwise the next person's action does it.
- Tests: `tests/page_bundles_dead_publish_test.php`. Not tried on a database with a real dead publish when it landed.

## What else is not covered?
- A save that fails part-way may have changed the page row before the failed query; the item is given back, the page is not restored.
