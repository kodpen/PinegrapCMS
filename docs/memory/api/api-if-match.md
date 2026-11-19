---
name: api-if-match
description: If-Match on an update of the external API: 412 precondition_failed when the record changed since, which updates honour it (GET twin), how the tag is read, no lock
---

## What does If-Match do on an update?
- An update sent with `If-Match` is refused with 412 `precondition_failed` (usual error envelope, nothing written) when the record no longer answers with that tag. Without the header nothing changes: the later write stands, as before.
- `*` matches any record that exists. Several tags may be sent comma-separated; one match is enough. `W/` is ignored on both sides, as for `If-None-Match`.
- No record (the GET answers anything but 200): nothing matches, `*` included, so the answer is 412 (RFC 9110 13.1.1).

## Which updates honour it?
- An update is a route reached by PATCH or PUT (its own method or `also_accepts`). It honours `If-Match` only when a GET route has the same `path` (`api_update_get_twin()`). Elsewhere the header is ignored.
- Counted on 2026-11-17: 37 of the 40 core updates; 57 of 67 with ERP, Workspace and Translate on. Without a twin: `calendars.update`, `content_review.update`, `menus.items.update`, several workspace updates.
- The OpenAPI document lists the `If-Match` header and a 412 answer only on the updates that honour it (`api_openapi_build()`). The MCP tools are unchanged: MCP runs its own dispatch and sends no `If-Match`.

## Which tag is compared?
- The one the GET of the same path answers this caller in full: no query, no `fields=`. `api_current_etag()` runs the GET handler inside the update request with method GET and an empty input. `api_send_capture()` makes `api_send()` throw its answer (`ApiAnswerCaptured`) before anything is printed, sent or logged, then everything is restored. The tag is `api_etag_of_json()`, the same function `api_send()` uses for the ETag header.

## When is it checked?
- In `api_run()` right before the handler: after the scope check, validation, the dry-run offer and the idempotency replay. A retry of a change that went through therefore gets its first answer, not a 412. A dry run is checked too.
- There is no lock. A write that lands between the read and the update is not caught. The Conventions text (topic ETag and If-Match) says so.

## Where is it tested?
- `tests/api_if_match_test.php`: the comparison, the twin, the router decision, and the run through the real `response.php` in a process of its own (`tests/helpers/api_if_match_run.php`). Code: `includes/api/preconditions.php`.
