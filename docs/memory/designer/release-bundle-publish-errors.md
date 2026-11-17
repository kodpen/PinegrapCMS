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

## What is still not covered?
- A fatal error or a timeout cannot be caught: the run stops where it is, with the same stuck state. Recovering such a bundle (giving back claimed items with no `written_state`, or taking back from `open`) is #10158: the publish holds the bundle's named lock (`GET_LOCK`), and the job or the next action on the bundle that gets it without waiting finishes the dead run as a failure.
- A save that fails part-way may have changed the page row before the failed query; the item is given back, the page is not restored.
